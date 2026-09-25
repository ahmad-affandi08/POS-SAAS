<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Pembelian\Data\DataPenerimaanBarang;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Layanan\PemrosesPenerimaanBarang;
use App\Domain\Pembelian\Layanan\PenyimpanLampiranPembelian;
use App\Domain\Pembelian\Layanan\PenyusunJurnalPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menerima & memposting barang (GRN) dari PO atau tanpa PO (F-04 fase 1, izin `pembelian.kelola`) dalam satu
 * transaksi bersama stok & jurnal (aturan #9–#10): dokumen + mutasi `PenerimaanPembelian` (`PemrosesPenerimaanBarang`)
 * lalu jurnal J-04.1 Dr persediaan, Cr `HutangBelumDifakturkan` sebesar nilai landed (penyeimbang Selisih HPP bila
 * BR-04.3). PPN yang dapat dikreditkan tidak dijurnal di GRN; ikut faktur. Audit `penerimaan-barang.posting`.
 *
 * Urutan kunci: L1 Tenant (S) → L2 PO → penghitung GR (L7) → buku stok (L3–L6) → penghitung JU (L7).
 */
final class TerimaBarang
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PengaturanPersediaanTenant $pengaturanPersediaan,
        private readonly InfoGudang $infoGudang,
        private readonly PemrosesPenerimaanBarang $pemroses,
        private readonly PenyusunJurnalPembelian $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PenyimpanLampiranPembelian $lampiran,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PesananTidakDikenal, PemasokTidakDikenal, GudangTidakDikenal, MelebihiPesanan, …
     */
    public function Jalankan(DataPenerimaanBarang $data): PenerimaanBarang
    {
        $berkas = $data->lampiran === null ? null : $this->lampiran->Simpan($this->konteks->Wajib(), 'penerimaan', $data->lampiran);

        try {
            return DB::transaction(fn (): PenerimaanBarang => $this->Proses($data, $berkas), 3);
        } catch (Throwable $galat) {
            if ($berkas !== null) {
                $this->lampiran->Hapus($berkas['PathLampiran']);
            }

            throw $galat;
        }
    }

    /**
     * @param  array{PathLampiran: string, NamaLampiran: string, MimeLampiran: string, UkuranLampiran: int}|null  $berkas
     */
    private function Proses(DataPenerimaanBarang $data, ?array $berkas): PenerimaanBarang
    {
        $this->pengaturanPersediaan->AmbilDenganKunciBaca();
        $po = null;

        if ($data->uuidPesananPembelian !== null) {
            $po = PesananPembelian::query()->where('Uuid', $data->uuidPesananPembelian)->lockForUpdate()->first();

            if ($po === null) {
                throw new PelanggaranAturanBisnis('PesananTidakDikenal', 'Pesanan pembelian tidak ditemukan.', 'UuidPesananPembelian');
            }
        }

        $pemasok = $po !== null
            ? Pemasok::query()->withTrashed()->whereKey($po->IdPemasok)->firstOrFail()
            : ($data->uuidPemasok === null ? null : Pemasok::query()->where('Uuid', $data->uuidPemasok)->where('Aktif', true)->first());

        if ($po === null && $data->uuidPemasok !== null && $pemasok === null) {
            throw new PelanggaranAturanBisnis('PemasokTidakDikenal', 'Pemasok tidak ditemukan atau nonaktif.', 'UuidPemasok');
        }

        $idGudang = $po !== null ? $po->IdGudang : $data->idGudang;
        $gudang = $idGudang === null ? null : ($this->infoGudang->AmbilBanyak([$idGudang])[$idGudang] ?? null);

        if ($gudang === null || ($po === null && ! $gudang->aktif)) {
            throw new PelanggaranAturanBisnis('GudangTidakDikenal', 'Lokasi stok tidak ditemukan atau diarsipkan.', 'UuidGudang');
        }

        $hasil = $this->pemroses->Buat($po, $pemasok, $gudang, $data->tanggal, $data->baris, $data->ongkir, $data->nomorSuratJalan, $data->catatan, $berkas, false, $data->idPengguna);
        $dokumen = $hasil['Dokumen'];

        $baris = PenyusunJurnalPembelian::Seimbangkan([
            ...$this->penyusunJurnal->BarisPersediaan($hasil['Mutasi'], $hasil['Produk'], $gudang->idOutlet),
            DataBarisJurnal::DariSelisih(PeranAkun::HutangBelumDifakturkan, Uang::Nol()->Kurangi($hasil['Mutasi']->TotalNilaiDiminta()), $gudang->idOutlet),
        ], $gudang->idOutlet);

        $jurnal = $baris === [] ? null : $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::PenerimaanBarang,
            idSumber: $dokumen->Id,
            uuidSumber: $dokumen->Uuid,
            nomorSumber: $dokumen->Nomor,
            tanggal: $data->tanggal,
            keterangan: mb_substr("Penerimaan barang {$dokumen->Nomor}".($pemasok === null ? '' : " dari {$pemasok->Nama}"), 0, 255),
            baris: $baris,
            idPengguna: $data->idPengguna,
        ));

        $dokumen->IdJurnal = $jurnal?->idJurnal;
        $dokumen->save();

        $this->riwayat->Catat(PenerimaanBarang::JENIS_DOKUMEN, $dokumen->Id, null, StatusDokumenPembelian::Diposting->value, $data->idPengguna);
        $this->audit->Catat('penerimaan-barang.posting', $dokumen, nilaiBaru: [
            'Nomor' => $dokumen->Nomor,
            'NomorPesanan' => $po?->Nomor,
            'Pemasok' => $pemasok?->Nama,
            'TotalNilai' => $dokumen->TotalNilai,
            'Pajak' => $dokumen->Pajak,
            'NomorJurnal' => $jurnal?->nomor,
        ], idPengguna: $data->idPengguna);

        return $dokumen;
    }
}

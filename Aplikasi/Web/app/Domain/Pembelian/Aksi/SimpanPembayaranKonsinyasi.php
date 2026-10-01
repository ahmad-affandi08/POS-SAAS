<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pembelian\Data\DataPembayaranKonsinyasi;
use App\Domain\Pembelian\Kueri\HutangKonsinyasi;
use App\Domain\Pembelian\Layanan\PemrosesPenerimaanBarang;
use App\Domain\Pembelian\Layanan\PenomorPembelian;
use App\Domain\Pembelian\Layanan\PenyusunJurnalPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PembayaranKonsinyasi;
use Illuminate\Support\Facades\DB;

/**
 * F-05i: setoran hasil penjualan titipan ke penitip (izin `pembelian.kelola`) dari akun kas/bank, boleh sebagian,
 * paling banyak sisa hutang konsinyasi penitip itu (`MelebihiHutang`). Jurnal: Dr Hutang Konsinyasi, Cr kas/bank.
 * Dikunci per penitip (baris `Pemasok` FOR UPDATE) supaya dua setoran bersamaan tidak melewati sisa. Audit
 * `konsinyasi.setor`.
 */
final class SimpanPembayaranKonsinyasi
{
    public function __construct(
        private readonly DaftarAkunPilihan $akun,
        private readonly HutangKonsinyasi $hutang,
        private readonly PemrosesPenerimaanBarang $pemroses,
        private readonly PenomorPembelian $penomor,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PemasokTidakDikenal, AkunKasBankWajib, JumlahTidakValid, MelebihiHutang, …
     */
    public function Jalankan(DataPembayaranKonsinyasi $data): PembayaranKonsinyasi
    {
        $akun = $this->akun->CariKasBankDariUuid($data->uuidAkun);

        if ($akun === null) {
            throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas/bank aktif sebagai sumber setoran.', 'UuidAkun');
        }

        if ($data->jumlah->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah setoran harus lebih dari Rp 0.', 'Jumlah');
        }

        $this->pemroses->PastikanTanggal($data->tanggal, null);

        return DB::transaction(function () use ($data, $akun): PembayaranKonsinyasi {
            $pemasok = Pemasok::query()->withTrashed()->where('Uuid', $data->uuidPemasok)->lockForUpdate()->first();

            if ($pemasok === null) {
                throw new PelanggaranAturanBisnis('PemasokTidakDikenal', 'Penitip tidak ditemukan.', 'UuidPemasok');
            }

            $sisa = $this->hutang->AmbilSisa($pemasok->Id);

            if ($data->jumlah->Bandingkan($sisa) > 0) {
                throw new PelanggaranAturanBisnis('MelebihiHutang', "Setoran paling banyak sisa hutang konsinyasi {$pemasok->Nama}: {$sisa->FormatRupiah()}.", 'Jumlah');
            }

            $catatan = $data->catatan === null ? '' : trim($data->catatan);
            $setoran = PembayaranKonsinyasi::query()->create([
                'Nomor' => $this->penomor->AmbilNomorTenant(JenisDokumenBernomor::PembayaranKonsinyasi, $data->tanggal),
                'IdPemasok' => $pemasok->Id,
                'Tanggal' => $data->tanggal->toDateString(),
                'Jumlah' => $data->jumlah->KeString(),
                'IdAkunKasBank' => $akun['Id'],
                'Status' => StatusDokumenTerposting::Diposting,
                'Catatan' => $catatan === '' ? null : mb_substr($catatan, 0, 500),
                'DibuatOleh' => $data->idPengguna,
            ]);

            $jurnal = $this->postingJurnal->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::PembayaranKonsinyasi,
                idSumber: $setoran->Id,
                uuidSumber: $setoran->Uuid,
                nomorSumber: $setoran->Nomor,
                tanggal: $data->tanggal,
                keterangan: mb_substr("Setoran konsinyasi {$setoran->Nomor} ke {$pemasok->Nama}", 0, 255),
                baris: array_values(array_filter([
                    DataBarisJurnal::Debit(PeranAkun::HutangKonsinyasi, $data->jumlah, null, $setoran->Nomor),
                    PenyusunJurnalPembelian::BarisAkun($akun['Id'], Uang::Nol()->Kurangi($data->jumlah), null, "Setoran {$setoran->Nomor}"),
                ], fn (?DataBarisJurnal $b): bool => $b !== null)),
                idPengguna: $data->idPengguna,
            ));

            $setoran->IdJurnal = $jurnal->idJurnal;
            $setoran->save();

            $this->riwayat->Catat(PembayaranKonsinyasi::JENIS_DOKUMEN, $setoran->Id, null, StatusDokumenTerposting::Diposting->value, $data->idPengguna);
            $this->audit->Catat('konsinyasi.setor', $setoran, nilaiBaru: [
                'Nomor' => $setoran->Nomor,
                'Penitip' => $pemasok->Nama,
                'Jumlah' => $setoran->Jumlah,
                'NomorJurnal' => $jurnal->nomor,
            ], idPengguna: $data->idPengguna);

            return $setoran;
        }, 3);
    }
}

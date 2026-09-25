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
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pembelian\Data\DataPembayaranHutang;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Enum\StatusFakturPembelian;
use App\Domain\Pembelian\Layanan\PemrosesPenerimaanBarang;
use App\Domain\Pembelian\Layanan\PenomorPembelian;
use App\Domain\Pembelian\Layanan\PenyimpanLampiranPembelian;
use App\Domain\Pembelian\Layanan\PenyusunJurnalPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PembayaranHutang;
use App\Domain\Pembelian\Model\PembayaranHutangAlokasi;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembayaran hutang (F-04 fase 1, izin `pembelian.kelola`) dari akun kas/bank (`Akun.KasBank`) untuk satu atau banyak
 * faktur satu pemasok, boleh sebagian: tiap alokasi > 0 dan ≤ sisa faktur. Jurnal J-04.4: Dr `HutangUsaha` per outlet
 * faktur, Cr akun kas/bank. Status faktur ikut sisa (DibayarSebagian/Lunas). Audit `pembayaran-hutang.posting`.
 *
 * Urutan kunci: faktur (urut Id) → pembayaran baru → penghitung BH & JU (L7).
 */
final class SimpanPembayaranHutang
{
    public const MAKS_FAKTUR = 100;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DaftarAkunPilihan $akun,
        private readonly PemrosesPenerimaanBarang $pemroses,
        private readonly PenomorPembelian $penomor,
        private readonly PostingJurnal $postingJurnal,
        private readonly PenyimpanLampiranPembelian $lampiran,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PemasokTidakDikenal, AkunKasBankWajib, AlokasiTidakValid, MelebihiSisa, …
     */
    public function Jalankan(DataPembayaranHutang $data): PembayaranHutang
    {
        $akun = $this->akun->CariKasBankDariUuid($data->uuidAkun);

        if ($akun === null) {
            throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas/bank aktif sebagai sumber pembayaran.', 'UuidAkun');
        }

        $berkas = $data->lampiran === null ? null : $this->lampiran->Simpan($this->konteks->Wajib(), 'pembayaran', $data->lampiran);

        try {
            return DB::transaction(fn (): PembayaranHutang => $this->Proses($data, $akun['Id'], $berkas), 3);
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
    private function Proses(DataPembayaranHutang $data, int $idAkun, ?array $berkas): PembayaranHutang
    {
        $pemasok = Pemasok::query()->withTrashed()->where('Uuid', $data->uuidPemasok)->first();

        if ($pemasok === null) {
            throw new PelanggaranAturanBisnis('PemasokTidakDikenal', 'Pemasok tidak ditemukan.', 'UuidPemasok');
        }

        if ($data->alokasi === [] || count($data->alokasi) > self::MAKS_FAKTUR) {
            throw new PelanggaranAturanBisnis('AlokasiTidakValid', 'Pilih 1 sampai '.self::MAKS_FAKTUR.' faktur yang dibayar.', 'Alokasi');
        }

        $faktur = FakturPembelian::query()->whereIn('Uuid', array_keys($data->alokasi))->orderBy('Id')->lockForUpdate()->get()->keyBy('Uuid');
        $total = Uang::Nol();
        $idOutlet = [];

        foreach ($data->alokasi as $uuid => $jumlah) {
            $f = $faktur->get((string) $uuid);

            if (! $f instanceof FakturPembelian || $f->IdPemasok !== $pemasok->Id || $f->BelanjaStok
                || $f->Status === StatusFakturPembelian::Dibatalkan || $f->Status === StatusFakturPembelian::Lunas) {
                throw new PelanggaranAturanBisnis('AlokasiTidakValid', 'Faktur yang dibayar harus faktur terbuka dari '.$pemasok->Nama.'.', "Alokasi.{$uuid}");
            }

            if ($jumlah->Bandingkan(Uang::Nol()) <= 0 || $jumlah->Bandingkan($f->AmbilSisa()) > 0) {
                throw new PelanggaranAturanBisnis('MelebihiSisa', "Pembayaran {$f->Nomor} harus lebih dari Rp 0 dan paling banyak sisa hutangnya {$f->AmbilSisa()->FormatRupiah()}.", "Alokasi.{$uuid}");
            }

            $total = $total->Tambah($jumlah);
            $idOutlet[(int) $f->IdOutlet] = $f->IdOutlet;
        }

        $outlet = count($idOutlet) === 1 ? array_values($idOutlet)[0] : null;
        $this->pemroses->PastikanTanggal($data->tanggal, $outlet);

        $pembayaran = PembayaranHutang::query()->create([
            'Nomor' => $this->penomor->AmbilNomorTenant(JenisDokumenBernomor::PembayaranHutang, $data->tanggal),
            'IdPemasok' => $pemasok->Id,
            'IdAkun' => $idAkun,
            'IdOutlet' => $outlet,
            'Tanggal' => $data->tanggal->toDateString(),
            'Jumlah' => $total->KeString(),
            'Status' => StatusDokumenPembelian::Diposting,
            'Catatan' => $data->catatan === null || trim($data->catatan) === '' ? null : mb_substr(trim($data->catatan), 0, 500),
            'DibuatOleh' => $data->idPengguna,
            ...($berkas ?? []),
        ]);

        $baris = [];

        foreach ($data->alokasi as $uuid => $jumlah) {
            /** @var FakturPembelian $f */
            $f = $faktur->get((string) $uuid);
            PembayaranHutangAlokasi::query()->create(['IdPembayaranHutang' => $pembayaran->Id, 'IdFakturPembelian' => $f->Id, 'Jumlah' => $jumlah->KeString()]);
            $asal = $f->Status;
            $f->JumlahDibayar = Uang::Dari($f->JumlahDibayar)->Tambah($jumlah)->KeString();
            $f->SelaraskanStatus();
            $f->save();

            if ($asal !== $f->Status) {
                $this->riwayat->Catat(FakturPembelian::JENIS_DOKUMEN, $f->Id, $asal->value, $f->Status->value, $data->idPengguna);
            }

            $baris[] = DataBarisJurnal::Debit(PeranAkun::HutangUsaha, $jumlah, $f->IdOutlet, $f->Nomor);
        }

        $baris[] = PenyusunJurnalPembelian::BarisAkun($idAkun, Uang::Nol()->Kurangi($total), $outlet, "Pembayaran {$pembayaran->Nomor}");
        $jurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::PembayaranHutang,
            idSumber: $pembayaran->Id,
            uuidSumber: $pembayaran->Uuid,
            nomorSumber: $pembayaran->Nomor,
            tanggal: $data->tanggal,
            keterangan: mb_substr("Pembayaran hutang {$pembayaran->Nomor} ke {$pemasok->Nama}", 0, 255),
            baris: array_values(array_filter($baris, fn (?DataBarisJurnal $b): bool => $b !== null)),
            idPengguna: $data->idPengguna,
        ));

        $pembayaran->IdJurnal = $jurnal->idJurnal;
        $pembayaran->save();

        $this->riwayat->Catat(PembayaranHutang::JENIS_DOKUMEN, $pembayaran->Id, null, StatusDokumenPembelian::Diposting->value, $data->idPengguna);
        $this->audit->Catat('pembayaran-hutang.posting', $pembayaran, nilaiBaru: [
            'Nomor' => $pembayaran->Nomor,
            'Pemasok' => $pemasok->Nama,
            'Jumlah' => $pembayaran->Jumlah,
            'Faktur' => $faktur->map(fn (FakturPembelian $f): string => $f->Nomor)->values()->all(),
            'NomorJurnal' => $jurnal->nomor,
        ], idPengguna: $data->idPengguna);

        return $pembayaran;
    }
}

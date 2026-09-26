<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiDeposit;
use App\Domain\Pelanggan\Enum\SumberMutasiDeposit;
use App\Domain\Pelanggan\Layanan\BukuDeposit;
use App\Domain\Pelanggan\Model\MutasiDeposit;
use App\Domain\Pelanggan\Model\Pelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sesuaikan deposit dari back-office (F-16d bagian 1, izin `pelanggan.deposit.kelola`): koreksi ± tanpa uang
 * keluar/masuk (salah input, kompensasi, atau deposit hangus sesuai ketentuan toko). Pengurangan tidak boleh membuat
 * saldo minus; alasan ≥ 5 karakter. Jurnal ke Pendapatan Lain-lain: tambah = Dr Pendapatan Lain-lain, Cr Saldo Deposit;
 * kurang = Dr Saldo Deposit, Cr Pendapatan Lain-lain. Audit `pelanggan.deposit-sesuaikan`.
 */
final class SesuaikanDeposit
{
    public const BATAS = '100000000';

    public function __construct(
        private readonly BukuDeposit $buku,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Pelanggan $pelanggan, Uang $bertanda, string $alasan, int $idPengguna, CarbonImmutable $tanggal): MutasiDeposit
    {
        if ($bertanda->BernilaiNol()) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Isi jumlah penyesuaian selain Rp 0.', 'Jumlah');
        }

        $besaran = $bertanda->BernilaiNegatif() ? Uang::Nol()->Kurangi($bertanda) : $bertanda;
        self::PeriksaJumlah($besaran, 'Jumlah');
        $alasan = self::PeriksaAlasan($alasan);

        return DB::transaction(function () use ($pelanggan, $bertanda, $besaran, $alasan, $idPengguna, $tanggal): MutasiDeposit {
            $saldo = $this->buku->AmbilSaldoTerkunci($pelanggan->Id);

            if ($bertanda->BernilaiNegatif() && $saldo->Tambah($bertanda)->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('SaldoDepositKurang', "Saldo deposit hanya {$saldo->FormatRupiah()}.", 'Jumlah');
            }

            $mutasi = $this->buku->Catat($pelanggan->Id, $bertanda, JenisMutasiDeposit::Penyesuaian, SumberMutasiDeposit::Manual, null, null, $tanggal, $alasan, $idPengguna);
            $baris = $bertanda->BernilaiNegatif()
                ? [DataBarisJurnal::Debit(PeranAkun::DepositPelanggan, $besaran, null, $alasan), DataBarisJurnal::Kredit(PeranAkun::PendapatanLain, $besaran, null, $alasan)]
                : [DataBarisJurnal::Debit(PeranAkun::PendapatanLain, $besaran, null, $alasan), DataBarisJurnal::Kredit(PeranAkun::DepositPelanggan, $besaran, null, $alasan)];
            $this->Posting($mutasi, $pelanggan, $tanggal, "Penyesuaian deposit {$pelanggan->Nama}", $baris, $idPengguna);
            $this->audit->Catat('pelanggan.deposit-sesuaikan', $pelanggan, ['SaldoDeposit' => $saldo->KeString()], [
                'SaldoDeposit' => $mutasi->SaldoSetelah,
                'Jumlah' => $bertanda->KeString(),
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $mutasi;
        });
    }

    /**
     * @param  list<DataBarisJurnal>  $baris
     */
    private function Posting(MutasiDeposit $mutasi, Pelanggan $pelanggan, CarbonImmutable $tanggal, string $keterangan, array $baris, int $idPengguna): void
    {
        $mutasi->IdJurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::MutasiDeposit,
            idSumber: $mutasi->Id,
            uuidSumber: $mutasi->Uuid,
            nomorSumber: null,
            tanggal: $tanggal,
            keterangan: mb_substr($keterangan, 0, 255),
            baris: $baris,
            idPengguna: $idPengguna,
        ))->idJurnal;
        $mutasi->save();
    }

    private static function PeriksaJumlah(Uang $jumlah, string $bidang): void
    {
        if ($jumlah->Bandingkan(Uang::Nol()) <= 0 || ! str_ends_with($jumlah->KeString(), '.00') || $jumlah->Bandingkan(Uang::Dari(self::BATAS)) > 0) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah harus Rupiah bulat lebih dari Rp 0, paling banyak Rp 100.000.000.', $bidang);
        }
    }

    private static function PeriksaAlasan(string $alasan): string
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan minimal 5 karakter.', 'Alasan');
        }

        return mb_substr($alasan, 0, 255);
    }
}

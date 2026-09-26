<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
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
 * Tarik deposit dari back-office (F-16d bagian 1, izin `pelanggan.deposit.kelola`): uang deposit dikembalikan ke
 * pelanggan dari akun kas/bank yang dipilih. Jumlah Rupiah bulat > 0, ≤ saldo, maks. Rp 100.000.000; alasan ≥ 5
 * karakter. Mutasi `Penarikan` (−) menjadi sumber jurnal (`JenisSumberJurnal::MutasiDeposit`): Dr Saldo Deposit
 * Pelanggan, Cr akun kas/bank. Audit `pelanggan.deposit-tarik`.
 */
final class TarikDeposit
{
    public const BATAS = '100000000';

    public function __construct(
        private readonly BukuDeposit $buku,
        private readonly DaftarAkunPilihan $akun,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Pelanggan $pelanggan, Uang $jumlah, string $uuidAkunKasBank, string $alasan, int $idPengguna, CarbonImmutable $tanggal): MutasiDeposit
    {
        self::PeriksaJumlah($jumlah, 'Jumlah');
        $alasan = self::PeriksaAlasan($alasan);
        $akun = $this->akun->CariKasBankDariUuid($uuidAkunKasBank)
            ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas/bank aktif sumber pengembalian.', 'UuidAkun');

        return DB::transaction(function () use ($pelanggan, $jumlah, $akun, $alasan, $idPengguna, $tanggal): MutasiDeposit {
            $saldo = $this->buku->AmbilSaldoTerkunci($pelanggan->Id);

            if ($jumlah->Bandingkan($saldo) > 0) {
                throw new PelanggaranAturanBisnis('SaldoDepositKurang', "Saldo deposit hanya {$saldo->FormatRupiah()}.", 'Jumlah');
            }

            $mutasi = $this->buku->Catat($pelanggan->Id, Uang::Nol()->Kurangi($jumlah), JenisMutasiDeposit::Penarikan, SumberMutasiDeposit::Manual, null, null, $tanggal, $alasan, $idPengguna, $akun['Id']);
            $this->Posting($mutasi, $pelanggan, $tanggal, "Tarik deposit {$pelanggan->Nama}", [
                DataBarisJurnal::Debit(PeranAkun::DepositPelanggan, $jumlah, null, $alasan),
                new DataBarisJurnal(null, $akun['Id'], null, Uang::Nol(), $jumlah, $alasan),
            ], $idPengguna);
            $this->audit->Catat('pelanggan.deposit-tarik', $pelanggan, ['SaldoDeposit' => $saldo->KeString()], [
                'SaldoDeposit' => $mutasi->SaldoSetelah,
                'Jumlah' => $jumlah->KeString(),
                'Akun' => $akun['Kode'],
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

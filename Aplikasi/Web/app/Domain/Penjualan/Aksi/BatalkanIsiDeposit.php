<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Layanan\PencatatDepositPenjualan;
use App\Domain\Penjualan\Enum\StatusIsiDeposit;
use App\Domain\Penjualan\Model\IsiDeposit;
use Illuminate\Support\Facades\DB;

/**
 * Batal isi deposit dari back-office (F-16d bagian 1, izin `pelanggan.deposit.kelola`): dokumen isi tidak diedit;
 * statusnya menjadi `Dibatalkan`, saldo deposit dikurangi (mutasi `BatalIsi`, hanya bila saldo pelanggan masih cukup
 * — deposit yang sudah dipakai tidak bisa dibatalkan), dan jurnal J-16.1 dibalik pada tanggal bisnis outlet hari ini.
 * Pengembalian uang ke pelanggan dilakukan di luar sistem kas shift (kas/bank back-office). Alasan wajib ≥ 5 karakter.
 * Audit `deposit.isi-batal`.
 */
final class BatalkanIsiDeposit
{
    public function __construct(
        private readonly PencatatDepositPenjualan $deposit,
        private readonly BalikkanJurnal $balikkan,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(IsiDeposit $isi, string $alasan, int $idPengguna): IsiDeposit
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan pembatalan minimal 5 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($isi, $alasan, $idPengguna): IsiDeposit {
            $isi = IsiDeposit::query()->whereKey($isi->Id)->lockForUpdate()->firstOrFail();

            if (! $isi->Status->BisaBerubahKe(StatusIsiDeposit::Dibatalkan)) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Isi deposit ini sudah dibatalkan.');
            }

            $jumlah = Uang::Dari($isi->Jumlah);

            if ($isi->IdPelanggan !== null) {
                $masalah = $this->deposit->PeriksaBisaBatalIsi($isi->IdPelanggan, $jumlah);

                if ($masalah !== null) {
                    throw new PelanggaranAturanBisnis('SaldoDepositKurang', $masalah);
                }
            }

            $tanggal = $this->tanggalBisnis->Hitung($isi->IdOutlet);

            if ($isi->IdPelanggan !== null) {
                $this->deposit->BatalkanIsi($isi->IdPelanggan, $isi->Id, $isi->Nomor, $jumlah, $tanggal, $idPengguna, $alasan);
            }

            if ($isi->IdJurnal !== null) {
                $isi->IdJurnalBatal = $this->balikkan->Jalankan(
                    $isi->IdJurnal,
                    $tanggal,
                    mb_substr("Batal isi deposit {$isi->Nomor}: {$alasan}", 0, 255),
                    JenisSumberJurnal::IsiDeposit,
                    $isi->Id,
                    'batal',
                    $idPengguna,
                )->idJurnal;
            }

            $isi->Status = StatusIsiDeposit::Dibatalkan;
            $isi->DibatalkanPada = now();
            $isi->AlasanBatal = mb_substr($alasan, 0, 255);
            $isi->IdPembatal = $idPengguna;
            $isi->save();

            $this->riwayat->Catat(IsiDeposit::JENIS_DOKUMEN, $isi->Id, StatusIsiDeposit::Diterima->value, StatusIsiDeposit::Dibatalkan->value, $idPengguna, $alasan);
            $this->audit->Catat('deposit.isi-batal', $isi, ['Status' => StatusIsiDeposit::Diterima->value], [
                'Status' => StatusIsiDeposit::Dibatalkan->value,
                'Nomor' => $isi->Nomor,
                'Jumlah' => $isi->Jumlah,
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $isi;
        });
    }
}

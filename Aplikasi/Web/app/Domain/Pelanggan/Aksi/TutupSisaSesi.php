<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Layanan\BukuSesi;
use App\Domain\Pelanggan\Layanan\PenutupSisaSesi;
use App\Domain\Pelanggan\Model\MutasiSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tutup sisa paket sesi dari back-office (F-16d bagian 2, izin `pelanggan.sesi.kelola`): pengganti retur untuk baris
 * paket. `Refund` = sisa nilai dikembalikan dari akun kas/bank yang dipilih; `Hangus` = sisa nilai diakui sebagai
 * pendapatan lain (misal pelanggan tidak datang lagi). Hanya saldo `Aktif`; alasan ≥ 5 karakter. Audit
 * `pelanggan.sesi-tutup`.
 */
final class TutupSisaSesi
{
    public function __construct(
        private readonly BukuSesi $buku,
        private readonly PenutupSisaSesi $penutup,
        private readonly DaftarAkunPilihan $akun,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(SaldoSesi $saldo, JenisMutasiSesi $jenis, ?string $uuidAkunKasBank, string $alasan, int $idPengguna, CarbonImmutable $tanggal): MutasiSesi
    {
        if (! in_array($jenis, [JenisMutasiSesi::Refund, JenisMutasiSesi::Hangus], true)) {
            throw new PelanggaranAturanBisnis('JenisTidakValid', 'Pilih kembalikan uang atau hanguskan sisa sesi.', 'Jenis');
        }

        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan minimal 5 karakter.', 'Alasan');
        }

        $akun = null;

        if ($jenis === JenisMutasiSesi::Refund) {
            $akun = ($uuidAkunKasBank === null ? null : $this->akun->CariKasBankDariUuid($uuidAkunKasBank))
                ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas/bank aktif sumber pengembalian.', 'UuidAkun');
        }

        return DB::transaction(function () use ($saldo, $jenis, $akun, $alasan, $idPengguna, $tanggal): MutasiSesi {
            $terkunci = $this->buku->Kunci($saldo->Id);

            if ($terkunci === null || $terkunci->Status !== StatusSaldoSesi::Aktif) {
                throw new PelanggaranAturanBisnis('SaldoSesiTidakAktif', 'Paket sesi ini sudah tidak aktif.', 'Status');
            }

            $lama = ['SisaSesi' => $terkunci->SisaSesi, 'NilaiTersisa' => $terkunci->NilaiTersisa];
            $mutasi = $this->penutup->Tutup($terkunci, $jenis, SumberMutasiSesi::Manual, $tanggal, mb_substr($alasan, 0, 255), $idPengguna, $akun['Id'] ?? null);
            $this->audit->Catat('pelanggan.sesi-tutup', $terkunci, $lama, [
                'Jenis' => $jenis->value,
                'Status' => $terkunci->Status->value,
                'Akun' => $akun['Kode'] ?? null,
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $mutasi;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Akuntansi\Aksi\JalankanJadwalKasBank;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Illuminate\Console\Command;
use Throwable;

/**
 * D-23 D bagian 2: tiap pagi mencatat transaksi kas & bank berulang yang jatuh tempo (per tenant, tanggal bisnis).
 * Kegagalan satu tenant tidak menghentikan tenant lain.
 */
final class JalankanJadwalKasBankPerintah extends Command
{
    protected $signature = 'akuntansi:jalankan-jadwal-kas-bank {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Mencatat transaksi kas & bank berulang yang jatuh tempo (D-23 D).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, TanggalBisnisOutlet $tanggalBisnis, JalankanJadwalKasBank $jalankan): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $dicatat = 0;
        $ditolak = 0;
        $galat = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                try {
                    $hasil = $jalankan->Jalankan($tanggalBisnis->Hitung(null));
                    $dicatat += $hasil['Dicatat'];
                    $ditolak += $hasil['Gagal'];
                } catch (Throwable $e) {
                    $galat++;
                    report($e);
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$dicatat} transaksi berulang dicatat, {$ditolak} ditolak, {$galat} tenant gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}

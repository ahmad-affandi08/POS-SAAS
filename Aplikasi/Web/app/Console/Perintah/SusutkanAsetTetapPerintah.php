<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Akuntansi\Aksi\SusutkanAsetTetap;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Illuminate\Console\Command;
use Throwable;

/**
 * FIN-10: tiap pagi menjurnal penyusutan aset tetap sampai **bulan lalu** (bulan berjalan baru disusutkan setelah
 * tutup bulan, atau lewat tombol "Susutkan sekarang"). Idempoten; bulan yang tertinggal ikut disusul. Kegagalan satu
 * tenant tidak menghentikan tenant lain.
 */
final class SusutkanAsetTetapPerintah extends Command
{
    protected $signature = 'akuntansi:susutkan-aset-tetap {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Menjurnal penyusutan bulanan aset tetap sampai bulan lalu (FIN-10).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, TanggalBisnisOutlet $tanggalBisnis, SusutkanAsetTetap $susutkan): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $dicatat = 0;
        $galat = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                try {
                    $bulanLalu = $tanggalBisnis->Hitung(null)->startOfMonth()->subMonthNoOverflow()->format('Y-m');
                    $dicatat += $susutkan->Jalankan($bulanLalu)['Dicatat'];
                } catch (Throwable $e) {
                    $galat++;
                    report($e);
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$dicatat} penyusutan bulanan dijurnal, {$galat} tenant gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}

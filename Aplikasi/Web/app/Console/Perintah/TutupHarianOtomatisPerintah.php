<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Aksi\TutupHarianOtomatis;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\PemilikTenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * D-23 D bagian 3: tiap pagi menutup otomatis hari yang aman ditutup di semua outlet (atas nama Owner tenant).
 */
final class TutupHarianOtomatisPerintah extends Command
{
    protected $signature = 'kasir:tutup-harian-otomatis {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Menutup otomatis hari yang aman ditutup (semua shift ditutup, tanpa peringatan) (D-23 D).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, PemilikTenant $pemilik, TutupHarianOtomatis $tutup): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $ditutup = 0;
        $gagal = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);
                $idPemilik = $pemilik->AmbilIdPemilik($idTenant);

                if ($idPemilik === null) {
                    continue;
                }

                try {
                    $ditutup += $tutup->Jalankan($idTenant, $idPemilik);
                } catch (Throwable $e) {
                    $gagal++;
                    report($e);
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$ditutup} hari ditutup otomatis, {$gagal} tenant gagal.");

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Bersama\Tindakan\Aksi\KirimRingkasanTindakanHarian;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Illuminate\Console\Command;
use Throwable;

/**
 * D-23 D bagian 4: tiap pagi (setelah otomatisasi lain) mengirim ringkasan Kotak Tindakan lewat email ke pelanggannya.
 * Kegagalan satu tenant tidak menghentikan tenant lain.
 */
final class KirimRingkasanTindakanPerintah extends Command
{
    protected $signature = 'tindakan:kirim-ringkasan-harian {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Mengirim ringkasan pagi Kotak Tindakan lewat email (D-23 D).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, TanggalBisnisOutlet $tanggalBisnis, KirimRingkasanTindakanHarian $kirim): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $terkirim = 0;
        $galat = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                try {
                    $terkirim += $kirim->Jalankan($idTenant, $tanggalBisnis->Hitung(null));
                } catch (Throwable $e) {
                    $galat++;
                    report($e);
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$terkirim} ringkasan terkirim, {$galat} tenant gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bengkel\Aksi\KirimPengingatServisOtomatis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bengkel (§9.10): tiap pagi mengantrekan pengingat WhatsApp servis berkala H-3 per tenant. Kegagalan satu tenant tidak
 * menghentikan tenant lain; tiap perintah kerja paling banyak diingatkan sekali per tanggal servis.
 */
final class KirimPengingatServisPerintah extends Command
{
    protected $signature = 'bengkel:kirim-pengingat-servis {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Mengantrekan pengingat servis berkala H-3 lewat WhatsApp (bengkel §9.10).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, TanggalBisnisOutlet $tanggalBisnis, KirimPengingatServisOtomatis $kirim): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $antre = 0;
        $galat = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                try {
                    $antre += $kirim->Jalankan($idTenant, $tanggalBisnis->Hitung(null));
                } catch (Throwable $e) {
                    $galat++;
                    report($e);
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$antre} pengingat servis diantrekan, {$galat} tenant gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}

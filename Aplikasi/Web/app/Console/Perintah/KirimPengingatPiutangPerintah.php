<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Aksi\KirimPengingatPiutangOtomatis;
use Illuminate\Console\Command;
use Throwable;

/**
 * D-23 D bagian 4b: tiap pagi (jam wajar untuk pelanggan) mengantrekan pengingat piutang otomatis per tenant yang
 * mengaktifkannya. Kegagalan satu tenant tidak menghentikan tenant lain.
 */
final class KirimPengingatPiutangPerintah extends Command
{
    protected $signature = 'pelanggan:kirim-pengingat-piutang {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Mengantrekan pengingat piutang jatuh tempo ke pelanggan lewat WhatsApp/email (D-23 D).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, TanggalBisnisOutlet $tanggalBisnis, KirimPengingatPiutangOtomatis $kirim): int
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

        $this->line(count($daftar)." tenant diperiksa, {$antre} pengingat piutang diantrekan, {$galat} tenant gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}

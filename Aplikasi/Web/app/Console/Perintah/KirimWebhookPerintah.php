<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Aksi\ProsesKirimanWebhookJatuhTempo;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use Illuminate\Console\Command;

/**
 * X7 bagian 2 (PRD §16.4 "dikirim oleh queue via cron"): tiap menit mengantrekan kiriman webhook yang jatuh tempo coba
 * ulang. Per tenant dengan `KonteksTenant` diatur sehingga semua kueri tetap lewat scope `MilikTenant`.
 */
final class KirimWebhookPerintah extends Command
{
    protected $signature = 'integrasi:kirim-webhook';

    protected $description = 'Mengantrekan kiriman webhook keluar yang jatuh tempo coba ulang (X7).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, ProsesKirimanWebhookJatuhTempo $proses): int
    {
        $sebelumnya = $konteks->Ambil();
        $diantrekan = 0;

        try {
            foreach ($keanggotaan->AmbilSemuaIdTenant() as $idTenant) {
                $konteks->Atur($idTenant);
                $diantrekan += $proses->Jalankan($idTenant);
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$diantrekan} kiriman webhook diantrekan.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\PemeriksaRelasiSilangTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Memeriksa rujukan foreign key lintas tenant (audit PAY-P1-02, `PemeriksaRelasiSilangTenant`). Keluar dengan kode 1
 * dan mencatat kritis di log bila ada temuan. Dijadwalkan mingguan di `routes/console.php`.
 */
final class PeriksaRelasiSilangTenantPerintah extends Command
{
    protected $signature = 'tenant:periksa-silang';

    protected $description = 'Memeriksa baris yang merujuk baris tenant lain lewat foreign key (isolasi tenant).';

    public function handle(PemeriksaRelasiSilangTenant $pemeriksa): int
    {
        $temuan = $pemeriksa->Periksa();

        if ($temuan === []) {
            $this->info('Tidak ada rujukan lintas tenant.');

            return self::SUCCESS;
        }

        foreach ($temuan as $t) {
            $this->error("{$t['Tabel']}.{$t['Kolom']} → {$t['TabelInduk']}: {$t['Jumlah']} baris merujuk tenant lain.");
        }

        Log::critical('Rujukan lintas tenant terdeteksi.', ['Temuan' => $temuan]);

        return self::FAILURE;
    }
}

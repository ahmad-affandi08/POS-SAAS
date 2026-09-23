<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Tenant\Aksi\AkhiriTrialKedaluwarsa;
use Illuminate\Console\Command;

/**
 * BR-00.3: dijalankan terjadwal setiap jam (routes/console.php).
 */
final class AkhiriTrialPerintah extends Command
{
    protected $signature = 'tenant:akhiri-trial';

    protected $description = 'Menurunkan langganan trial yang sudah berakhir ke paket Gratis (BR-00.3).';

    public function handle(AkhiriTrialKedaluwarsa $akhiri): int
    {
        $this->info("{$akhiri->Jalankan()} langganan trial diturunkan ke Gratis.");

        return self::SUCCESS;
    }
}

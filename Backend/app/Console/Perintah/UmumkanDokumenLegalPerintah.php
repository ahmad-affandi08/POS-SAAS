<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Tenant\Aksi\UmumkanDokumenLegalMateriil;
use Illuminate\Console\Command;

/**
 * BR-P06.5: dijalankan terjadwal setiap hari (routes/console.php).
 */
final class UmumkanDokumenLegalPerintah extends Command
{
    protected $signature = 'tenant:umumkan-dokumen-legal';

    protected $description = 'Mengirim email pengumuman versi materiil dokumen legal yang terjadwal ke semua Owner (BR-P06.5).';

    public function handle(UmumkanDokumenLegalMateriil $umumkan): int
    {
        $this->info("{$umumkan->Jalankan()} email pengumuman dokumen legal terkirim.");

        return self::SUCCESS;
    }
}

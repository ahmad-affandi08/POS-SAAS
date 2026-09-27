<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Situs\Aksi\BersihkanProspekSitus;
use Illuminate\Console\Command;

/**
 * Situs pemasaran bagian B: hapus prospek yang melewati masa simpan (terjadwal harian, routes/console.php).
 */
final class BersihkanProspekSitusPerintah extends Command
{
    protected $signature = 'situs:bersihkan-prospek';

    protected $description = 'Menghapus prospek situs yang melewati masa simpan (Spam 30 hari, lainnya 24 bulan).';

    public function handle(BersihkanProspekSitus $bersihkan): int
    {
        $this->info("{$bersihkan->Jalankan()} prospek dihapus.");

        return self::SUCCESS;
    }
}

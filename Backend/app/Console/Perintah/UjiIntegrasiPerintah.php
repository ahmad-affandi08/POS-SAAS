<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Integrasi\Aksi\UjiIntegrasiBerkala;
use Illuminate\Console\Command;

/**
 * BR-P05.3: dijalankan terjadwal setiap jam (routes/console.php).
 */
final class UjiIntegrasiPerintah extends Command
{
    protected $signature = 'pengelola:uji-integrasi';

    protected $description = 'Menguji koneksi integrasi platform yang aktif dan mengirim alert bila gagal (BR-P05.3).';

    public function handle(UjiIntegrasiBerkala $uji): int
    {
        $hasil = $uji->Jalankan();
        $this->info("{$hasil['Diuji']} integrasi diuji, {$hasil['BaruGagal']} baru gagal.");

        return self::SUCCESS;
    }
}

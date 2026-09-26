<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Rilis\Aksi\SegarkanKompatibilitasPerangkat;
use Illuminate\Console\Command;

/**
 * HCL (PRD §17.2.5a, v1.98): menyegarkan daftar kompatibilitas perangkat dari hasil Wizard Uji Perangkat, harian.
 */
final class SegarkanKompatibilitasPerintah extends Command
{
    protected $signature = 'pengelola:segarkan-kompatibilitas';

    protected $description = 'Menyegarkan daftar kompatibilitas perangkat & printer dari profil hardware (HCL).';

    public function handle(SegarkanKompatibilitasPerangkat $segarkan): int
    {
        $hasil = $segarkan->Jalankan();
        $this->info("Daftar kompatibilitas: {$hasil['Perangkat']} model perangkat, {$hasil['Printer']} printer.");

        return self::SUCCESS;
    }
}

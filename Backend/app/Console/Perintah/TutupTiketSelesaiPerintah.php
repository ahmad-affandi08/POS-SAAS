<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Dukungan\Aksi\TutupTiketSelesaiOtomatis;
use Illuminate\Console\Command;

/**
 * P-09: tiket `Selesai` yang tidak dibuka lagi dalam 7 hari ditutup (terjadwal harian, routes/console.php).
 */
final class TutupTiketSelesaiPerintah extends Command
{
    protected $signature = 'pengelola:tutup-tiket-selesai';

    protected $description = 'Menutup tiket dukungan yang sudah selesai lebih dari 7 hari (P-09).';

    public function handle(TutupTiketSelesaiOtomatis $tutup): int
    {
        $this->info("{$tutup->Jalankan()} tiket ditutup.");

        return self::SUCCESS;
    }
}

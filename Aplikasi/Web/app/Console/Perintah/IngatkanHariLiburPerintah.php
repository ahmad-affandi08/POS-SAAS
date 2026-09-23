<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Referensi\Aksi\KirimPengingatHariLibur;
use Illuminate\Console\Command;

/**
 * BR-P02.4: dijalankan terjadwal setiap hari (routes/console.php).
 */
final class IngatkanHariLiburPerintah extends Command
{
    protected $signature = 'pengelola:ingatkan-hari-libur';

    protected $description = 'Mengingatkan Konten & Legal bila hari libur tahun berikutnya belum terbit (BR-P02.4).';

    public function handle(KirimPengingatHariLibur $kirim): int
    {
        $jumlah = $kirim->Jalankan(now());
        $this->info($jumlah === 0 ? 'Tidak ada pengingat yang perlu dikirim.' : "Pengingat dikirim ke {$jumlah} anggota.");

        return self::SUCCESS;
    }
}

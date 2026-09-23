<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Tagihan\Aksi\ProsesTunggakanLangganan;
use Illuminate\Console\Command;

/**
 * P-08 langkah 4 / F-00: jatuh tempo → Tertunggak → Ditangguhkan. Dijadwalkan tiap jam (routes/console.php).
 */
final class ProsesTunggakanPerintah extends Command
{
    protected $signature = 'tagihan:proses-tunggakan';

    protected $description = 'Menandai tagihan lewat jatuh tempo dan memproses langganan Tertunggak/Ditangguhkan (P-08).';

    public function handle(ProsesTunggakanLangganan $proses): int
    {
        $hasil = $proses->Jalankan();
        $this->info("{$hasil['TagihanJatuhTempo']} tagihan lewat jatuh tempo, {$hasil['Tertunggak']} langganan Tertunggak, {$hasil['Ditangguhkan']} langganan Ditangguhkan.");

        return self::SUCCESS;
    }
}

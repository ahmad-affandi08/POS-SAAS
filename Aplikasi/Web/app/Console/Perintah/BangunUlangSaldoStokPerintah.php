<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use Illuminate\Console\Command;
use LogicException;

/**
 * Membangun ulang / memeriksa saldo stok semua tenant (DesainF05a C.9). `--periksa` keluar dengan kode 1 bila ada
 * perbedaan.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim H (DesainF05a G).
 */
final class BangunUlangSaldoStokPerintah extends Command
{
    protected $signature = 'persediaan:bangun-ulang-saldo {--tenant=* : Id tenant (kosong = semua)} {--periksa : Hanya memeriksa, keluar 1 bila berbeda}';

    protected $description = 'Membangun ulang atau memeriksa SaldoStok dari MutasiStok (F-05a).';

    public function handle(): int
    {
        throw new LogicException('F-05a Tim H');
    }
}

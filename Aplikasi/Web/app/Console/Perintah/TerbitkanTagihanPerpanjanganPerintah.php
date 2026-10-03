<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Tenant\Aksi\KirimPengingatTagihanLangganan;
use App\Domain\Tenant\Aksi\TerbitkanTagihanPerpanjanganOtomatis;
use Illuminate\Console\Command;

/**
 * P-08 langkah 1, 2 & 4 (PRD v4.04): tagihan perpanjangan otomatis H-7, lalu pengingat H-7/H-3/H0/H+3 ke Owner.
 * Penerbitan dijalankan lebih dulu supaya tagihan yang baru terbit langsung mendapat pengingat pertamanya.
 * Dijadwalkan sekali sehari di jam kerja (routes/console.php).
 */
final class TerbitkanTagihanPerpanjanganPerintah extends Command
{
    protected $signature = 'tagihan:terbitkan-perpanjangan';

    protected $description = 'Menerbitkan tagihan perpanjangan langganan H-7 dan mengirim pengingat tagihan ke Owner (P-08).';

    public function handle(TerbitkanTagihanPerpanjanganOtomatis $terbitkan, KirimPengingatTagihanLangganan $pengingat): int
    {
        $hasil = $terbitkan->Jalankan();
        $diingatkan = $pengingat->Jalankan();
        $this->info("{$hasil['Diterbitkan']} tagihan perpanjangan terbit, {$hasil['Dilewati']} dilewati, {$diingatkan} tagihan diingatkan.");

        return self::SUCCESS;
    }
}

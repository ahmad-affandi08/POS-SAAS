<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Penjualan\Aksi\SiapkanMetodePembayaranBawaan;
use Illuminate\Console\Command;

/**
 * F-01: data bawaan panduan awal untuk tenant yang terdaftar sebelum F-01, yaitu metode pembayaran Tunai.
 * Idempoten; jalankan sekali saat deploy (bersama `organisasi:siapkan-peran` untuk izin `panduan-awal.kelola`).
 */
final class SiapkanBawaanPanduanAwalPerintah extends Command
{
    protected $signature = 'panduan-awal:siapkan-bawaan';

    protected $description = 'Menyiapkan data bawaan panduan awal (metode pembayaran Tunai) untuk tenant lama (F-01).';

    public function handle(KeanggotaanPengguna $keanggotaan, SiapkanMetodePembayaranBawaan $siapkan): int
    {
        $daftar = $keanggotaan->AmbilSemuaIdTenant();

        foreach ($daftar as $idTenant) {
            $siapkan->Jalankan($idTenant);
        }

        $this->info(count($daftar).' tenant disiapkan.');

        return self::SUCCESS;
    }
}

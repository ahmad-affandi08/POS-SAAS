<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Pengelola\Referensi\Aksi\SiapkanSatuanStandarBawaan;
use App\Domain\Pengelola\TimInternal\Aksi\SiapkanPeranBawaan;
use Illuminate\Database\Seeder;

/**
 * Nama kelas mengikuti Laravel (pengecualian §13.7.4).
 * Data awal Platform Pengelola (paket, template sektor, tarif pajak) ditambahkan bersama flow P-02 s.d. P-04.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(SiapkanPeranBawaan $siapkanPeranBawaan, SiapkanSatuanStandarBawaan $siapkanSatuanStandar): void
    {
        // P-01 langkah 2: tujuh peran internal bawaan beserta izinnya (PRD §19.3). Idempoten.
        $siapkanPeranBawaan->Jalankan();

        // P-02: satuan standar awal. Idempoten.
        $siapkanSatuanStandar->Jalankan();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Pengingat BR-P02.4: hari libur tahun berikutnya wajib terbit paling lambat 1 Desember.
 */
final class PengingatHariLibur extends SurelDasar
{
    public function __construct(public readonly int $tahun, public readonly bool $terlambat)
    {
        $this->subject($terlambat
            ? "Terlambat: hari libur {$tahun} belum terbit"
            : "Pengingat: terbitkan hari libur {$tahun} sebelum 1 Desember")
            ->IsiSurel('Pengelola.PengingatHariLibur', ['Tahun' => $tahun, 'Terlambat' => $terlambat]);
    }
}

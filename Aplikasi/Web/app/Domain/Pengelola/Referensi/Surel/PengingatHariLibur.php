<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Surel;

use Illuminate\Mail\Mailable;

/**
 * Pengingat BR-P02.4: hari libur tahun berikutnya wajib terbit paling lambat 1 Desember.
 */
final class PengingatHariLibur extends Mailable
{
    public function __construct(public readonly int $tahun, public readonly bool $terlambat)
    {
        $this->subject($terlambat
            ? "Terlambat: hari libur {$tahun} belum terbit"
            : "Pengingat: terbitkan hari libur {$tahun} sebelum 1 Desember")
            ->text('Surel.Pengelola.PengingatHariLibur', ['Tahun' => $tahun, 'Terlambat' => $terlambat]);
    }
}

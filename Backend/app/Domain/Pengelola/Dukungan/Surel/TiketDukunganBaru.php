<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Surel;

use Illuminate\Mail\Mailable;

/**
 * Pemberitahuan tiket baru ke tim Dukungan (P-09). Tidak memuat isi pesan tenant: dibaca di Platform Pengelola.
 */
final class TiketDukunganBaru extends Mailable
{
    public function __construct(
        public readonly string $nomor,
        public readonly string $judul,
        public readonly string $namaTenant,
        public readonly string $kategori,
        public readonly string $prioritas,
        public readonly string $batasSla,
        public readonly string $tautan,
    ) {
        $this->subject("[{$prioritas}] Tiket baru {$nomor}: {$judul}")
            ->text('Surel.Pengelola.TiketDukunganBaru', [
                'Nomor' => $nomor, 'Judul' => $judul, 'NamaTenant' => $namaTenant, 'Kategori' => $kategori,
                'Prioritas' => $prioritas, 'BatasSla' => $batasSla, 'Tautan' => $tautan,
            ]);
    }
}

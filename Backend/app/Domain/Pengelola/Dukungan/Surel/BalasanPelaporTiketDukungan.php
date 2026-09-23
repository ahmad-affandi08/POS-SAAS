<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Surel;

use Illuminate\Mail\Mailable;

/**
 * Pemberitahuan ke penanggung jawab tiket bahwa pelapor membalas atau membuka lagi tiket (P-09).
 */
final class BalasanPelaporTiketDukungan extends Mailable
{
    public function __construct(
        public readonly string $nomor,
        public readonly string $judul,
        public readonly bool $dibukaLagi,
        public readonly string $tautan,
    ) {
        $this->subject(($dibukaLagi ? 'Tiket dibuka lagi' : 'Balasan pelapor')." {$nomor}: {$judul}")
            ->text('Surel.Pengelola.BalasanPelaporTiketDukungan', [
                'Nomor' => $nomor, 'Judul' => $judul, 'DibukaLagi' => $dibukaLagi, 'Tautan' => $tautan,
            ]);
    }
}

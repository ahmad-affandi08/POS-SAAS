<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Surel;

use Illuminate\Mail\Mailable;

/**
 * Alert BR-P05.3: tes koneksi berkala gagal.
 */
final class IntegrasiGagal extends Mailable
{
    /**
     * @param  list<array{Label: string, Pesan: string}>  $gagal
     */
    public function __construct(public readonly array $gagal, public readonly string $lingkungan)
    {
        $this->subject("Integrasi gagal ({$lingkungan}): ".implode(', ', array_column($gagal, 'Label')))
            ->text('Surel.Pengelola.IntegrasiGagal', ['Gagal' => $gagal, 'Lingkungan' => $lingkungan]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Alert BR-P05.3: tes koneksi berkala gagal.
 */
final class IntegrasiGagal extends SurelDasar
{
    /**
     * @param  list<array{Label: string, Pesan: string}>  $gagal
     */
    public function __construct(public readonly array $gagal, public readonly string $lingkungan)
    {
        $this->subject("Integrasi gagal ({$lingkungan}): ".implode(', ', array_column($gagal, 'Label')))
            ->IsiSurel('Pengelola.IntegrasiGagal', ['Gagal' => $gagal, 'Lingkungan' => $lingkungan]);
    }
}

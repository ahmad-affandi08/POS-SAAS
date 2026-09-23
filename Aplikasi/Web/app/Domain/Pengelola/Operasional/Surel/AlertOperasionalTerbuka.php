<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Surel;

use Illuminate\Mail\Mailable;

/**
 * Alert BR-P11.1 / backup terlambat (P-11). Dikirim sekali per insiden.
 */
final class AlertOperasionalTerbuka extends Mailable
{
    public function __construct(
        public readonly string $label,
        public readonly string $tingkat,
        public readonly string $pesan,
        public readonly string $mulaiPada,
        public readonly string $tautan,
    ) {
        $this->subject('['.mb_strtoupper($tingkat)."] {$label} (".config('app.name').')')
            ->text('Surel.Pengelola.AlertOperasional', [
                'Label' => $label, 'Tingkat' => $tingkat, 'Pesan' => $pesan, 'MulaiPada' => $mulaiPada, 'Tautan' => $tautan,
            ]);
    }
}

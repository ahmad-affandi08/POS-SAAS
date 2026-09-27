<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Alert BR-P11.1 / backup terlambat (P-11). Dikirim sekali per insiden.
 */
final class AlertOperasionalTerbuka extends SurelDasar
{
    public function __construct(
        public readonly string $label,
        public readonly string $tingkat,
        public readonly string $pesan,
        public readonly string $mulaiPada,
        public readonly string $tautan,
    ) {
        $this->subject('['.mb_strtoupper($tingkat)."] {$label} (".config('app.name').')')
            ->IsiSurel('Pengelola.AlertOperasional', [
                'Label' => $label, 'Tingkat' => $tingkat, 'Pesan' => $pesan, 'MulaiPada' => $mulaiPada, 'Tautan' => $tautan,
            ]);
    }
}

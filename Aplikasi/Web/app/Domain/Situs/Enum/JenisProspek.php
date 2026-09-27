<?php

declare(strict_types=1);

namespace App\Domain\Situs\Enum;

/** Tujuan formulir prospek situs pemasaran: pertanyaan umum atau minta demo. */
enum JenisProspek: string
{
    case Kontak = 'Kontak';
    case Demo = 'Demo';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Kontak => 'Pertanyaan',
            self::Demo => 'Minta demo',
        };
    }
}

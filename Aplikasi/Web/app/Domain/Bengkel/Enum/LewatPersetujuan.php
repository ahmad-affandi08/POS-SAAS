<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Enum;

/** Asal keputusan persetujuan estimasi: tautan WhatsApp yang dibuka pelanggan, atau dicatat staf (langsung/telepon). */
enum LewatPersetujuan: string
{
    case Tautan = 'Tautan';
    case Staf = 'Staf';
}

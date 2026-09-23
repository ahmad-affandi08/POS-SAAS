<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Platform aplikasi POS (PRD §15.3 `Perangkat.Platform`, §14.6). Versi terbaru & minimal diatur per platform.
 */
enum PlatformPerangkat: string
{
    case Android = 'Android';
    case Ios = 'Ios';
    case Windows = 'Windows';
}

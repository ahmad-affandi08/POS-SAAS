<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Kanal rilis aplikasi (P-10 langkah 2, §14.6): `Beta` hanya untuk tenant berpenanda Uji/Internal (perangkat lab &
 * tenant uji), `Stabil` untuk semua tenant lewat rollout bertahap.
 */
enum KanalRilis: string
{
    case Beta = 'Beta';
    case Stabil = 'Stabil';
}

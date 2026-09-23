<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Enum;

/**
 * Asal catatan backup: perintah `pengelola:catat-backup` dari skrip backup server, atau isian manual Teknis.
 */
enum SumberCatatanBackup: string
{
    case Skrip = 'Skrip';
    case Manual = 'Manual';
}

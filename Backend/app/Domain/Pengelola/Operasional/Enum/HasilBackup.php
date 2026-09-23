<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Enum;

enum HasilBackup: string
{
    case Berhasil = 'Berhasil';
    case Gagal = 'Gagal';
}

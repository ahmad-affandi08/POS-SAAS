<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Enum;

enum KeputusanTinjauan: string
{
    case Setuju = 'Setuju';
    case Tolak = 'Tolak';
}

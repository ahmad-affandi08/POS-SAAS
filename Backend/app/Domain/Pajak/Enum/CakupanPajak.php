<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Enum;

enum CakupanPajak: string
{
    case Nasional = 'Nasional';
    case Daerah = 'Daerah';
    case Kustom = 'Kustom';
}

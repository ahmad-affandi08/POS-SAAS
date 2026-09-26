<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/** Baris Hardware Compatibility List: model perangkat (mesin kasir, tablet, PC) atau printer struk. */
enum JenisKompatibilitas: string
{
    case Perangkat = 'Perangkat';
    case Printer = 'Printer';
}

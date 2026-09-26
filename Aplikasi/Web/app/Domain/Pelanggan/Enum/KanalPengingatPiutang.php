<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Kanal pengingat piutang ke pelanggan (D-23 D bagian 4b). */
enum KanalPengingatPiutang: string
{
    case Whatsapp = 'Whatsapp';
    case Email = 'Email';
}

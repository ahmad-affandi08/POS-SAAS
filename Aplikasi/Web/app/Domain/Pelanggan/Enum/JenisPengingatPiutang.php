<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Enum;

/** Jenis pengingat piutang (D-23 D bagian 4b): otomatis sebelum/lewat jatuh tempo, atau dikirim manual dari back-office. */
enum JenisPengingatPiutang: string
{
    case SebelumJatuhTempo = 'SebelumJatuhTempo';
    case LewatJatuhTempo = 'LewatJatuhTempo';
    case Manual = 'Manual';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::SebelumJatuhTempo => 'Sebelum jatuh tempo',
            self::LewatJatuhTempo => 'Lewat jatuh tempo',
            self::Manual => 'Manual',
        };
    }
}

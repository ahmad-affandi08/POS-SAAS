<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

enum JenisPemenuhanOnline: string
{
    case AmbilSendiri = 'AmbilSendiri';
    case Kirim = 'Kirim';

    public function AmbilLabel(): string
    {
        return $this === self::AmbilSendiri ? 'Ambil sendiri' : 'Dikirim';
    }
}

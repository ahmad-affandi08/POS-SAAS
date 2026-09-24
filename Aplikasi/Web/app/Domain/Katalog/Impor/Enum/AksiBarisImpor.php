<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Enum;

/** Rencana untuk satu baris impor (F-03): buat produk baru, perbarui produk yang ada, atau lewati. */
enum AksiBarisImpor: string
{
    case Buat = 'Buat';
    case Perbarui = 'Perbarui';
    case Lewati = 'Lewati';
}

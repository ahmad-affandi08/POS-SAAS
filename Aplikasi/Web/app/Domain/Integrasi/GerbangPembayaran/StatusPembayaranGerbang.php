<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

/**
 * Status tagihan di gerbang pembayaran, dipetakan dari status tiap penyedia.
 */
enum StatusPembayaranGerbang: string
{
    case Menunggu = 'Menunggu';
    case Lunas = 'Lunas';
    case Kedaluwarsa = 'Kedaluwarsa';
    case Gagal = 'Gagal';
}

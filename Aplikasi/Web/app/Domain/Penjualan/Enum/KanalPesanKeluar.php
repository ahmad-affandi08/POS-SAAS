<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/** Kanal pesan keluar ke pelanggan (K3 kirim struk digital). */
enum KanalPesanKeluar: string
{
    case Whatsapp = 'Whatsapp';
    case Email = 'Email';
}

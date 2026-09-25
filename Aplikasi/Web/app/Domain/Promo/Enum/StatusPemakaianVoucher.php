<?php

declare(strict_types=1);

namespace App\Domain\Promo\Enum;

/**
 * Status voucher untuk satu penjualan (F-16c bagian 2): `Dipesan` saat kasir memasukkan kode (online, sampai
 * `DipesanSampai`), `Dipakai` saat penjualannya diterima server, `Dilepas` bila kasir melepas voucher atau penjualan di-void.
 */
enum StatusPemakaianVoucher: string
{
    case Dipesan = 'Dipesan';
    case Dipakai = 'Dipakai';
    case Dilepas = 'Dilepas';
}

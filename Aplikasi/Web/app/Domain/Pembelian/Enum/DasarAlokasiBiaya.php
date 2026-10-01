<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

/** v3.41 (INV-14): dasar pembagian biaya tambahan ke baris penerimaan barang. */
enum DasarAlokasiBiaya: string
{
    /** Sebanding nilai baris (harga landed − nilai yang sudah diretur). Bawaan, cocok untuk asuransi & bea. */
    case Nilai = 'Nilai';

    /** Sebanding jumlah dalam satuan dasar (sudah dikurangi retur). Cocok untuk ongkir per koli/kg. */
    case Jumlah = 'Jumlah';

    public function AmbilLabel(): string
    {
        return $this === self::Nilai ? 'Sebanding nilai barang' : 'Sebanding jumlah barang';
    }
}

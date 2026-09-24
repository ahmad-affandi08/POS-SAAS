<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

/**
 * Cara menilai satu baris mutasi stok (DesainF05a C.2/C.3): `Ditentukan` = pemanggil memberi nilai (penerimaan,
 * stok awal, pembatalan), `Berjalan` = mesin HPP menilai dari saldo/lapisan berjalan (penjualan, transfer keluar).
 */
enum ModeNilaiMutasi: string
{
    case Ditentukan = 'Ditentukan';
    case Berjalan = 'Berjalan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Ditentukan => 'Nilai ditentukan dokumen',
            self::Berjalan => 'Nilai dari HPP berjalan',
        };
    }
}

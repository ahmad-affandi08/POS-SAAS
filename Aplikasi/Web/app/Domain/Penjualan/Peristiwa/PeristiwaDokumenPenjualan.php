<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Peristiwa;

/**
 * Kontrak peristiwa dokumen penjualan yang mengubah angka penjualan satu (tenant, outlet, tanggal bisnis): penjualan
 * diterima, di-void, atau diretur. Dipakai penangan ringkasan laporan (F-14a) untuk menghitung ulang baris terkait.
 */
interface PeristiwaDokumenPenjualan
{
    public function AmbilIdTenant(): int;

    public function AmbilIdOutlet(): int;

    /** `YYYY-MM-DD`. */
    public function AmbilTanggalBisnis(): string;
}

<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Model\Tenant;

/**
 * Mutex konfigurasi satu tenant (F-01): mengunci baris `Tenant` (FOR UPDATE) sehingga penerapan template, pengisian
 * pengaturan, penambahan produk cepat, dan metode pembayaran bawaan dari satu tenant diproses berurutan (kirim ganda
 * aman). Hanya di dalam transaksi.
 *
 * Urutan kunci di semua flow: Tenant → Langganan (PastikanBatasPaket) → Outlet → baris data.
 */
final class PenguncianTenant
{
    public function Kunci(int $idTenant): Tenant
    {
        return Tenant::query()->whereKey($idTenant)->lockForUpdate()->firstOrFail();
    }
}

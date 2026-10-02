<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Model\Penjualan;

/**
 * K-17 (§18.3 butir 7): dari Uuid penjualan yang baru dikirim perangkat, mana yang diterima dengan tanda
 * `PerluTinjauan` (stok kurang, pesanan dibayar ganda, periode terkunci, ...). Ditampilkan di layar Status sinkron
 * kasir agar kasir tahu transaksinya akan diperiksa back-office; alasannya tidak dikirim ke perangkat.
 */
final class PenjualanPerluTinjauan
{
    /**
     * @param  list<string>  $uuid
     * @return list<string>
     */
    public function AmbilUuid(array $uuid): array
    {
        if ($uuid === []) {
            return [];
        }

        return array_values(Penjualan::query()
            ->whereIn('Uuid', $uuid)
            ->where('PerluTinjauan', true)
            ->orderBy('Id')
            ->pluck('Uuid')
            ->map(fn (mixed $u): string => (string) $u)
            ->all());
    }
}

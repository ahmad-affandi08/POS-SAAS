<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Model\Meja;
use Carbon\CarbonImmutable;

/**
 * K-12 (§9.1): status "perlu dibersihkan" meja. Ditandai saat pesanan meja dibayar (Penjualan memanggil
 * [TandaiPerluDibersihkan]) dan dihapus saat pelayan/kasir menandai bersih ([TandaiBersih], outbox `Meja.Bersih`).
 * Urutan waktu perangkat dihormati: tanda bersih yang lebih tua dari pembayaran terakhir tidak menghapus statusnya,
 * jadi sinkron yang terlambat tidak membuat meja kotor tampak siap.
 */
final class AturKebersihanMeja
{
    public function TandaiPerluDibersihkan(int $idMeja, CarbonImmutable $sejak): void
    {
        $meja = Meja::query()->whereKey($idMeja)->lockForUpdate()->first();

        if ($meja instanceof Meja && ($meja->PerluDibersihkanSejak === null || $meja->PerluDibersihkanSejak->lessThan($sejak))) {
            $meja->update(['PerluDibersihkanSejak' => $sejak]);
        }
    }

    /** True bila status dihapus; meja tidak dikenal/outlet lain = false (item tetap diterima, tidak ada yang berubah). */
    public function TandaiBersih(int $idOutlet, string $uuidMeja, CarbonImmutable $dibersihkanPada): bool
    {
        $meja = Meja::query()->where('IdOutlet', $idOutlet)->where('Uuid', $uuidMeja)->lockForUpdate()->first();

        if (! $meja instanceof Meja || $meja->PerluDibersihkanSejak === null || $meja->PerluDibersihkanSejak->greaterThan($dibersihkanPada)) {
            return false;
        }

        $meja->update(['PerluDibersihkanSejak' => null]);

        return true;
    }
}

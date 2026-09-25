<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\Produk;

/** Nama produk per Uuid untuk domain lain (F-16c: produk pemicu promo di formulir & daftar promo). */
final class NamaProduk
{
    /**
     * @param  list<string>  $uuid
     * @return array<string, string> kunci = Uuid
     */
    public function Ambil(array $uuid): array
    {
        if ($uuid === []) {
            return [];
        }

        return Produk::query()->withTrashed()->whereIn('Uuid', array_values(array_unique($uuid)))->pluck('Nama', 'Uuid')->map(fn ($n): string => (string) $n)->all();
    }
}

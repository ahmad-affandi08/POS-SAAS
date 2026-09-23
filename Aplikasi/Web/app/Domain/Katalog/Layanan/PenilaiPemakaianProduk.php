<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Contracts\Container\Container;

/**
 * BR-03.2: menilai apakah produk sudah dipakai lewat semua `PemeriksaPemakaianProduk` yang ditandai tag
 * `PemeriksaPemakaianProduk::TAG`. Alasan pertama yang tidak null menang (urutan pendaftaran tag).
 */
final class PenilaiPemakaianProduk
{
    public function __construct(private readonly Container $kontainer) {}

    public function AmbilAlasan(Produk $produk): ?string
    {
        foreach ($this->kontainer->tagged(PemeriksaPemakaianProduk::TAG) as $pemeriksa) {
            if (! $pemeriksa instanceof PemeriksaPemakaianProduk) {
                continue;
            }

            $alasan = $pemeriksa->PeriksaPemakaian($produk->Id);

            if ($alasan !== null) {
                return $alasan;
            }
        }

        return null;
    }
}

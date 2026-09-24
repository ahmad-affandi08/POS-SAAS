<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;

/**
 * Pemakaian batas `BatasSku` (BR-P04.3, DesainF03 H9): produk tenant aktif yang belum dihapus, tidak diarsipkan,
 * dan bukan induk varian (anak-anak varian yang dihitung). `LangkahBerikutnya` F-01 ikut memakai hitungan ini.
 */
final class PemakaianSku
{
    public function Hitung(): int
    {
        return Produk::query()
            ->whereNull('DiarsipkanPada')
            ->where('Jenis', '!=', JenisProduk::IndukVarian->value)
            ->count();
    }
}

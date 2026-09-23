<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

/**
 * Ringkasan hasil penyimpanan harga: jumlah baris `ProdukHarga` yang ditambah, diubah, dan dihapus. Semuanya 0 bila
 * tidak ada perbedaan (tidak ada yang ditulis).
 */
final readonly class HasilSimpanHarga
{
    public function __construct(
        public int $ditambah,
        public int $diubah,
        public int $dihapus,
    ) {}

    public function CekAdaPerubahan(): bool
    {
        return $this->ditambah + $this->diubah + $this->dihapus > 0;
    }
}

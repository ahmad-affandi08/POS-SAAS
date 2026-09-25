<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;

/**
 * Produk yang dirujuk baris penjualan (F-07b, `KomposisiPenjualan`), termasuk yang sudah diarsipkan/dihapus setelah
 * dijual offline. `satuan` = satuan produk per Uuid `ProdukSatuan` (`IdSatuan`, `KonversiKeDasar` string 4 desimal).
 * `idKelompokPajak` & `hargaTermasukPajak` (null = ikut outlet) untuk mencocokkan snapshot pajak (PRD v1.46).
 */
final readonly class DataProdukPenjualan
{
    /**
     * @param  array<string, array{IdSatuan: int, KonversiKeDasar: string}>  $satuan
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $nama,
        public JenisProduk $jenis,
        public PelacakanProduk $pelacakan,
        public int $idSatuanDasar,
        public array $satuan,
        public bool $dihapus,
        public ?int $idKelompokPajak = null,
        public ?bool $hargaTermasukPajak = null,
        public ?string $uuidKategori = null,
    ) {}
}

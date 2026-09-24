<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;

/**
 * Ringkasan produk untuk domain Persediaan (DesainF05a C.1, `InfoProdukStok`). `bolehMinus` null = ikut pengaturan
 * tenant. `simbolSatuan`/`bolehDesimal` dari satuan dasar.
 */
final readonly class DataInfoProdukStok
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $nama,
        public ?string $sku,
        public JenisProduk $jenis,
        public PelacakanProduk $pelacakan,
        public ?bool $bolehMinus,
        public int $idSatuanDasar,
        public string $simbolSatuan,
        public bool $bolehDesimal,
        public bool $diarsipkan,
        public bool $dihapus,
    ) {}
}

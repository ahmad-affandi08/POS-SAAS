<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;

/**
 * Baris pembelian yang sudah diperiksa & dihitung (`PenyelesaiBarisPembelian`): produk, satuan pembelian beserta
 * konversi ke satuan dasar, jumlah (satuan pembelian) & jumlah dasar, harga per satuan pembelian, diskon, bruto
 * (Jumlah × Harga dibulatkan per baris) dan subtotal (bruto − diskon).
 */
final readonly class DataBarisTerhitung
{
    public function __construct(
        public DataInfoProdukStok $produk,
        public ?int $idProdukSatuan,
        public string $simbolSatuan,
        public Kuantitas $konversi,
        public Kuantitas $jumlah,
        public Kuantitas $jumlahDasar,
        public Uang $harga,
        public Uang $diskon,
        public Uang $bruto,
        public Uang $subtotal,
    ) {}
}

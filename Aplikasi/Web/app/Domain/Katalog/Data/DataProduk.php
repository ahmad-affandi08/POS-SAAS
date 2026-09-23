<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Enum\SumberPerubahanKatalog;

/**
 * Isi lengkap produk dari form back-office atau impor (F-03 `SimpanProduk`). `uuid` = kunci idempotensi saat membuat.
 * `sku` kosong/null = dibuat otomatis. `hargaTermasukPajak`/`bolehMinus` null = ikut outlet/tenant.
 * `bolehUbahHarga` = pelaku memegang izin `produk.harga.ubah` (syarat `hargaAwal`).
 */
final readonly class DataProduk
{
    /**
     * @param  list<DataSatuanProduk>  $satuan
     * @param  list<DataAtributVarian>  $atributVarian
     */
    public function __construct(
        public string $uuid,
        public string $nama,
        public ?string $namaStruk,
        public ?string $sku,
        public JenisProduk $jenis,
        public ?int $idKategori,
        public ?string $merek,
        public int $idSatuanDasar,
        public PelacakanProduk $pelacakan,
        public ?int $idKelompokPajak,
        public ?bool $hargaTermasukPajak,
        public ?bool $bolehMinus,
        public bool $tampilDiPos,
        public bool $tampilOnline,
        public array $satuan,
        public array $atributVarian,
        public bool $bolehUbahHarga,
        public SumberPerubahanKatalog $sumber = SumberPerubahanKatalog::Manual,
    ) {}
}

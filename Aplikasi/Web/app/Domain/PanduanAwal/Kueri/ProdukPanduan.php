<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Kueri;

use App\Domain\Katalog\Kueri\KatalogPanduan;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Organisasi\Data\DataOutletRingkas;
use App\Domain\PanduanAwal\Layanan\PembacaIsiTemplate;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;

/**
 * Data langkah 4 panduan awal (F-01): produk contoh dari versi template yang diterapkan (ditandai bila namanya sudah
 * ada), kategori tenant, 50 produk terbaru, dan pemakaian batas `BatasSku` paket.
 */
final class ProdukPanduan
{
    public const JUMLAH_PRODUK_TERBARU = 50;

    public function __construct(
        private readonly TemplateTerbit $templateTerbit,
        private readonly PembacaIsiTemplate $pembaca,
        private readonly KatalogPanduan $katalog,
        private readonly PemakaianSku $pemakaianSku,
        private readonly PastikanBatasPaket $batasPaket,
    ) {}

    /**
     * Bentuk `PropsProdukPanduan` tanpa `Progres` (kontrak frontend §E).
     *
     * @return array<string, mixed>
     */
    public function Ambil(DataOutletRingkas $outlet): array
    {
        $versi = $this->templateTerbit->CariVersi($outlet->idTemplateSektorVersi);
        $contoh = $versi === null ? [] : $this->pembaca->Baca($versi->Isi)->produkContoh;
        $namaAda = $contoh === [] ? [] : array_flip($this->katalog->AmbilNamaProdukAda());
        $jumlah = $this->pemakaianSku->Hitung();

        return [
            'AdaTemplate' => $versi !== null,
            'ProdukContoh' => array_map(fn ($satu): array => [
                'Nama' => $satu->nama,
                'NamaKategori' => $satu->kategori,
                'Harga' => $satu->harga,
                'KodeSatuan' => $satu->kodeSatuan,
                'SudahAda' => isset($namaAda[mb_strtolower($satu->nama)]),
            ], $contoh),
            'Kategori' => $this->katalog->AmbilKategori(),
            'Produk' => $this->katalog->AmbilProdukTerbaru(self::JUMLAH_PRODUK_TERBARU),
            'JumlahProduk' => $jumlah,
            'BatasSku' => $this->batasPaket->AmbilRingkasan($outlet->idTenant, 'BatasSku', $jumlah),
        ];
    }
}

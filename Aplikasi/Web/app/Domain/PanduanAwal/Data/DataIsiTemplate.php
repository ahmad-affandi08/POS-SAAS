<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Data;

use App\Domain\Akuntansi\Data\DataAkunTemplate;
use App\Domain\Pajak\Data\DataKelompokPajakTemplate;

/**
 * Isi versi template sektor yang sudah dibaca defensif (F-01). Bagian yang rusak menjadi kosong.
 */
final readonly class DataIsiTemplate
{
    /**
     * @param  list<DataAkunTemplate>  $akun
     * @param  array<string, string>  $pemetaanAkun
     * @param  list<string>  $kategori
     * @param  list<string>  $kodeSatuan
     * @param  list<DataKelompokPajakTemplate>  $kelompokPajak
     * @param  list<string>  $kunciFitur
     * @param  list<string>  $modeKasir
     * @param  list<DataProdukContohTemplate>  $produkContoh
     * @param  list<string>  $stasiunDapur
     */
    public function __construct(
        public array $akun,
        public array $pemetaanAkun,
        public array $kategori,
        public array $kodeSatuan,
        public array $kelompokPajak,
        public array $kunciFitur,
        public array $modeKasir,
        public ?string $modeKasirDefault,
        public DataPengaturanTemplate $pengaturan,
        public array $produkContoh,
        public array $stasiunDapur = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use Carbon\CarbonImmutable;

/**
 * Masukan pembuatan surat jalan grosir (F-12, §9.7, BR-12.2). Baris menunjuk baris SO lewat **Urutan** (nomor baris
 * yang terlihat di dokumen), bukan Id basis data, dan **harga tidak dikirim klien**: seluruh harga & diskonnya disalin
 * dari snapshot SO yang sudah dikonfirmasi.
 */
final readonly class DataSuratJalan
{
    /**
     * @param  list<DataBarisSuratJalan>  $baris
     */
    public function __construct(
        public string $uuidPesanan,
        public int $idGudang,
        public CarbonImmutable $tanggal,
        public array $baris,
        public ?string $namaPengirim = null,
        public ?string $nomorKendaraan = null,
        public ?string $namaPenerima = null,
        public ?string $catatan = null,
    ) {}
}

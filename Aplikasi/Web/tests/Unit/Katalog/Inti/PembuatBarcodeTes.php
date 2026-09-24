<?php

declare(strict_types=1);

use App\Domain\Katalog\Layanan\PembuatBarcode;
use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Layanan\PenyusunAnakVarian;

describe('F-03 BR-03.1 digit periksa EAN-13 barcode internal', function (): void {
    it('menghitung digit periksa dengan bobot 1,3 dari kiri', function (string $duaBelasDigit, int $harapan): void {
        expect(PembuatBarcode::HitungDigitPeriksa($duaBelasDigit))->toBe($harapan);
    })->with([
        'EAN contoh GS1' => ['400638133393', 1],
        'ISBN-13 9780306406157' => ['978030640615', 7],
        'internal pertama' => ['200000000001', 5],
        'internal kedua' => ['200000000002', 2],
        'kelipatan 10' => ['000000000000', 0],
    ]);
});

describe('F-03 kunci varian dan path gambar', function (): void {
    it('kunci varian huruf kecil, dipangkas, urut atribut induk', function (): void {
        expect(PenyusunAnakVarian::BuatKunci([['Nama' => ' Ukuran ', 'Nilai' => 'XL '], ['Nama' => 'Warna', 'Nilai' => 'Merah Marun']]))
            ->toBe('ukuran=xl|warna=merah marun');
    });

    it('path gambar kecil = akhiran -kecil sebelum ekstensi', function (): void {
        expect(PenyimpanGambarProduk::PathKecil('produk/7/01JB-01JC.webp'))->toBe('produk/7/01JB-01JC-kecil.webp');
    });
});

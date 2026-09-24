<?php

declare(strict_types=1);

/*
 * Master produk, harga & pajak (F-03). Kunci PascalCase (D-05). Perubahan lewat Tim 1 Katalog Inti.
 */
return [
    // BR-03.1: SKU otomatis `PRD-000001` dan barcode internal EAN-13 berawalan `20` (H8: bisa bentrok label timbangan).
    'Sku' => ['Awalan' => 'PRD-'],
    'Barcode' => ['Awalan' => '20'],

    // Gambar produk: disk privat (unduh lewat rute terautentikasi); S3 cukup ganti disk.
    'DiskGambar' => env('KATALOG_DISK_GAMBAR', 'local'),
    'Gambar' => [
        'UkuranMaksimalKb' => 5120,
        'SisiBesar' => 800,
        'SisiKecil' => 256,
        'Kualitas' => 80,
        // Batas piksel (lebar × tinggi) sebelum didekode GD: mencegah bom piksel (PNG kecil berdimensi raksasa).
        'PikselMaksimal' => 40000000,
    ],

    'Varian' => [
        'MaksimalAtribut' => 3,
        'MaksimalNilai' => 20,
        'MaksimalKombinasi' => 100,
    ],

    'Kategori' => ['MaksimalKedalaman' => 3],

    // BR-03.6: batas berkas impor; di atas `BatasBarisSinkron` baris diproses di antrean per potongan.
    'Impor' => [
        'UkuranMaksimalKb' => 10240,
        'MaksimalBaris' => 20000,
        'BatasBarisSinkron' => 300,
        'UkuranPotongan' => 50,
        'MaksimalDetikPerTugas' => 40,
        'HariSimpan' => 30,
    ],

    // Katalog POS: tumpang tindih kursor delta, dan kursor lebih tua dari ini = sinkron lengkap.
    'Pos' => [
        'TumpangTindihDetik' => 120,
        'UmurKursorMaksimalHari' => 90,
    ],
];

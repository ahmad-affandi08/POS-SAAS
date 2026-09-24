<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * QA F-03 gambar produk: "pixel flood" (bom dekompresi). PNG kecil (puluhan KB) dengan dimensi raksasa lolos batas
 * ukuran berkas, tetapi GD mendekodenya ke memori penuh (lebar × tinggi × bita per piksel) sehingga satu unggahan
 * menghabiskan ratusan MB RAM/CPU. Dimensi harus ditolak sebelum didekode.
 */

/** PNG skala abu-abu 1-bit berdimensi `lebar × tinggi`, isi nol (terkompresi sangat kecil). */
function BuatPngBomPikselQa(int $lebar, int $tinggi): string
{
    $potongan = static fn (string $jenis, string $isi): string => pack('N', strlen($isi)).$jenis.$isi.pack('N', crc32($jenis.$isi));
    $barisMentah = str_repeat("\x00".str_repeat("\x00", intdiv($lebar + 7, 8)), $tinggi);

    return "\x89PNG\r\n\x1a\n"
        .$potongan('IHDR', pack('NNCCCCC', $lebar, $tinggi, 1, 0, 0, 0, 0))
        .$potongan('IDAT', (string) gzcompress($barisMentah, 9))
        .$potongan('IEND', '');
}

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Storage::fake('local');
    config(['katalog.DiskGambar' => 'local']);
});

it('menolak PNG kecil berdimensi raksasa (bom piksel) sebelum didekode, tanpa menyimpan berkas', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    $produk = BantuanKatalog::BuatProduk([], null, $t['Pcs']);
    $isi = BuatPngBomPikselQa(7000, 7000);
    expect(strlen($isi))->toBeLessThan(200 * 1024);

    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)
        ->post("/kelola/produk/{$produk->Uuid}/gambar", ['Gambar' => UploadedFile::fake()->createWithContent('bom.png', $isi)])
        ->assertSessionHasErrors(['Gambar']);

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect($produk->refresh()->PathGambar)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('tetap menerima foto ponsel biasa 4000×3000', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    $produk = BantuanKatalog::BuatProduk([], null, $t['Pcs']);

    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)
        ->post("/kelola/produk/{$produk->Uuid}/gambar", ['Gambar' => UploadedFile::fake()->createWithContent('foto.png', BuatPngBomPikselQa(4000, 3000))])
        ->assertSessionHasNoErrors();

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect($produk->refresh()->PathGambar)->not->toBeNull();
});

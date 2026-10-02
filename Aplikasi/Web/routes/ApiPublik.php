<?php

declare(strict_types=1);

use App\Domain\Integrasi\ApiPublik\Enum\CakupanApi;
use App\Http\Kontroler\ApiPublik\V1\DataKontroler;
use App\Http\Kontroler\ApiPublik\V1\StokKontroler;
use App\Http\Perantara\AutentikasiTokenApi;
use Illuminate\Support\Facades\Route;

/*
 * X7 Open API v1 (PRD §16.1 lapisan Publik): token API tenant bercakupan, prefix `/api/v1`, nama rute `api.v1.*`.
 * Bagian 1 baca; bagian 4 tulis (`stok:tulis`: penyesuaian stok). Batas 120 permintaan/menit per token (`api-publik`).
 */

$cakupan = static fn (CakupanApi $c): string => AutentikasiTokenApi::class.':'.$c->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware('throttle:api-publik')->group(function () use ($cakupan, $ulid): void {
    Route::get('/produk', [DataKontroler::class, 'Produk'])->middleware($cakupan(CakupanApi::ProdukBaca))->name('api.v1.produk');
    Route::get('/produk/{uuidProduk}', [DataKontroler::class, 'ProdukSatu'])->middleware($cakupan(CakupanApi::ProdukBaca))->where('uuidProduk', $ulid)->name('api.v1.produk.satu');
    Route::get('/stok', [DataKontroler::class, 'Stok'])->middleware($cakupan(CakupanApi::StokBaca))->name('api.v1.stok');
    Route::post('/stok/penyesuaian', [StokKontroler::class, 'BuatPenyesuaian'])->middleware($cakupan(CakupanApi::StokTulis))->name('api.v1.stok.penyesuaian');
    Route::get('/penjualan', [DataKontroler::class, 'Penjualan'])->middleware($cakupan(CakupanApi::PenjualanBaca))->name('api.v1.penjualan');
    Route::get('/penjualan/{uuidPenjualan}', [DataKontroler::class, 'PenjualanSatu'])->middleware($cakupan(CakupanApi::PenjualanBaca))->where('uuidPenjualan', $ulid)->name('api.v1.penjualan.satu');
    Route::get('/pelanggan', [DataKontroler::class, 'Pelanggan'])->middleware($cakupan(CakupanApi::PelangganBaca))->name('api.v1.pelanggan');
});

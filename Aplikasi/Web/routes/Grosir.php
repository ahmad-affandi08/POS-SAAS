<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Grosir\FakturPenjualanKontroler;
use App\Http\Kontroler\Kelola\Grosir\PesananGrosirKontroler;
use App\Http\Kontroler\Kelola\Grosir\ProdukGrosirKontroler;
use App\Http\Kontroler\Kelola\Grosir\SuratJalanKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office grosir (F-12, §9.7, D-32): pesanan grosir (SO), surat jalan, dan faktur penjualan. Didaftarkan dari
 * routes/web.php di dalam grup `/kelola` (auth + IdentifikasiTenantSesi … BatasiTenantDitangguhkan). Semua rute memakai
 * `SiapkanAuditTenant` dan izin `grosir.kelola`; konfirmasi SO yang melewati limit kredit butuh `grosir.setujui-kredit`
 * (diperiksa di Aksi, BR-12.6, karena yang tahu paparannya hanya Aksi itu).
 * Parameter dokumen dibatasi pola ULID dan dicari lewat `MilikTenant` + batas outlet di kontroler (lainnya = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::GrosirKelola)])->prefix('grosir')->group(function () use ($ulid): void {
    Route::get('/produk/cari', [ProdukGrosirKontroler::class, 'Cari'])->name('kelola.grosir.produk.cari');
    Route::get('/pelanggan/cari', [ProdukGrosirKontroler::class, 'CariPelanggan'])->name('kelola.grosir.pelanggan.cari');

    Route::get('/pesanan', [PesananGrosirKontroler::class, 'Daftar'])->name('kelola.grosir.pesanan.daftar');
    Route::get('/pesanan/buat', [PesananGrosirKontroler::class, 'Buat'])->name('kelola.grosir.pesanan.buat');
    Route::post('/pesanan', [PesananGrosirKontroler::class, 'Simpan'])->name('kelola.grosir.pesanan.simpan');
    Route::get('/pesanan/{pesanan}', [PesananGrosirKontroler::class, 'Detail'])->where('pesanan', $ulid)->name('kelola.grosir.pesanan.detail');
    Route::get('/pesanan/{pesanan}/ubah', [PesananGrosirKontroler::class, 'Ubah'])->where('pesanan', $ulid)->name('kelola.grosir.pesanan.ubah');
    Route::put('/pesanan/{pesanan}', [PesananGrosirKontroler::class, 'Perbarui'])->where('pesanan', $ulid)->name('kelola.grosir.pesanan.perbarui');
    Route::post('/pesanan/{pesanan}/konfirmasi', [PesananGrosirKontroler::class, 'Konfirmasi'])->where('pesanan', $ulid)->name('kelola.grosir.pesanan.konfirmasi');
    Route::post('/pesanan/{pesanan}/kirim', [PesananGrosirKontroler::class, 'Kirim'])->where('pesanan', $ulid)->name('kelola.grosir.pesanan.kirim');
    Route::post('/pesanan/{pesanan}/batalkan', [PesananGrosirKontroler::class, 'Batalkan'])->where('pesanan', $ulid)->name('kelola.grosir.pesanan.batalkan');

    Route::get('/surat-jalan', [SuratJalanKontroler::class, 'Daftar'])->name('kelola.grosir.surat-jalan.daftar');
    Route::get('/surat-jalan/{suratJalan}', [SuratJalanKontroler::class, 'Detail'])->where('suratJalan', $ulid)->name('kelola.grosir.surat-jalan.detail');
    Route::post('/surat-jalan/{suratJalan}/batalkan', [SuratJalanKontroler::class, 'Batalkan'])->where('suratJalan', $ulid)->name('kelola.grosir.surat-jalan.batalkan');

    Route::get('/faktur', [FakturPenjualanKontroler::class, 'Daftar'])->name('kelola.grosir.faktur.daftar');
    Route::get('/faktur/buat', [FakturPenjualanKontroler::class, 'Buat'])->name('kelola.grosir.faktur.buat');
    Route::post('/faktur', [FakturPenjualanKontroler::class, 'Simpan'])->name('kelola.grosir.faktur.simpan');
    Route::get('/faktur/{faktur}', [FakturPenjualanKontroler::class, 'Detail'])->where('faktur', $ulid)->name('kelola.grosir.faktur.detail');
    Route::put('/faktur/{faktur}/nomor-pajak', [FakturPenjualanKontroler::class, 'UbahNomorPajak'])->where('faktur', $ulid)->name('kelola.grosir.faktur.nomor-pajak');
    Route::post('/faktur/{faktur}/batalkan', [FakturPenjualanKontroler::class, 'Batalkan'])->where('faktur', $ulid)->name('kelola.grosir.faktur.batalkan');
});

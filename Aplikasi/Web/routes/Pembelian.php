<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Pembelian\FakturPembelianKontroler;
use App\Http\Kontroler\Kelola\Pembelian\HutangKontroler;
use App\Http\Kontroler\Kelola\Pembelian\PemasokKontroler;
use App\Http\Kontroler\Kelola\Pembelian\PenerimaanBarangKontroler;
use App\Http\Kontroler\Kelola\Pembelian\PengaturanPembelianKontroler;
use App\Http\Kontroler\Kelola\Pembelian\PesananPembelianKontroler;
use App\Http\Kontroler\Kelola\Pembelian\ProdukPembelianKontroler;
use App\Http\Kontroler\Kelola\Pembelian\ReturPembelianKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-04 fase 1 pembelian (PRD "Rincian F-04 fase 1", §13.6, D-06): pemasok, pesanan pembelian,
 * penerimaan barang, belanja stok, faktur, hutang & pembayaran, retur, dan pengaturan. Didaftarkan dari
 * routes/web.php di dalam grup `/kelola` (auth + IdentifikasiTenantSesi … BatasiTenantDitangguhkan). Semua rute
 * memakai `SiapkanAuditTenant`; izin `pembelian.kelola`, persetujuan PO & pengaturan `pembelian.po.setujui`.
 * Parameter dokumen dibatasi pola ULID dan dicari lewat `MilikTenant` + batas outlet di kontroler (lainnya = 404).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::PembelianKelola)])->prefix('pembelian')->group(function () use ($ulid): void {
    Route::get('/produk/cari', [ProdukPembelianKontroler::class, 'Cari'])->name('kelola.pembelian.produk.cari');

    Route::get('/pemasok', [PemasokKontroler::class, 'Daftar'])->name('kelola.pembelian.pemasok.daftar');
    Route::get('/pemasok/buat', [PemasokKontroler::class, 'Buat'])->name('kelola.pembelian.pemasok.buat');
    Route::post('/pemasok', [PemasokKontroler::class, 'Simpan'])->name('kelola.pembelian.pemasok.simpan');
    Route::put('/pemasok/{pemasok}', [PemasokKontroler::class, 'Perbarui'])->where('pemasok', $ulid)->name('kelola.pembelian.pemasok.perbarui');
    Route::post('/pemasok/{pemasok}/status', [PemasokKontroler::class, 'UbahStatus'])->where('pemasok', $ulid)->name('kelola.pembelian.pemasok.status');
    Route::delete('/pemasok/{pemasok}', [PemasokKontroler::class, 'Hapus'])->where('pemasok', $ulid)->name('kelola.pembelian.pemasok.hapus');

    Route::get('/pesanan', [PesananPembelianKontroler::class, 'Daftar'])->name('kelola.pembelian.pesanan.daftar');
    Route::get('/pesanan/buat', [PesananPembelianKontroler::class, 'Buat'])->name('kelola.pembelian.pesanan.buat');
    Route::post('/pesanan', [PesananPembelianKontroler::class, 'Simpan'])->name('kelola.pembelian.pesanan.simpan');
    Route::post('/pesanan/draf-otomatis', [PesananPembelianKontroler::class, 'BuatDrafOtomatis'])->name('kelola.pembelian.pesanan.draf-otomatis');
    Route::get('/pesanan/{pesanan}', [PesananPembelianKontroler::class, 'Detail'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.detail');
    Route::get('/pesanan/{pesanan}/ubah', [PesananPembelianKontroler::class, 'Ubah'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.ubah');
    Route::put('/pesanan/{pesanan}', [PesananPembelianKontroler::class, 'Perbarui'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.perbarui');
    Route::post('/pesanan/{pesanan}/ajukan', [PesananPembelianKontroler::class, 'Ajukan'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.ajukan');
    Route::post('/pesanan/{pesanan}/batalkan', [PesananPembelianKontroler::class, 'Batalkan'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.batalkan');
    Route::post('/pesanan/{pesanan}/tutup', [PesananPembelianKontroler::class, 'Tutup'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.tutup');
    Route::get('/pesanan/{pesanan}/cetak', [PesananPembelianKontroler::class, 'Cetak'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.cetak');

    Route::get('/penerimaan', [PenerimaanBarangKontroler::class, 'Daftar'])->name('kelola.pembelian.penerimaan.daftar');
    Route::get('/penerimaan/buat', [PenerimaanBarangKontroler::class, 'Buat'])->name('kelola.pembelian.penerimaan.buat');
    Route::post('/penerimaan', [PenerimaanBarangKontroler::class, 'Simpan'])->name('kelola.pembelian.penerimaan.simpan');
    Route::get('/penerimaan/{penerimaan}', [PenerimaanBarangKontroler::class, 'Detail'])->where('penerimaan', $ulid)->name('kelola.pembelian.penerimaan.detail');
    Route::post('/penerimaan/{penerimaan}/batalkan', [PenerimaanBarangKontroler::class, 'Batalkan'])->where('penerimaan', $ulid)->name('kelola.pembelian.penerimaan.batalkan');
    Route::get('/penerimaan/{penerimaan}/lampiran', [PenerimaanBarangKontroler::class, 'Lampiran'])->where('penerimaan', $ulid)->name('kelola.pembelian.penerimaan.lampiran');

    Route::get('/belanja-stok', [PenerimaanBarangKontroler::class, 'BuatBelanja'])->name('kelola.pembelian.belanja-stok.buat');
    Route::post('/belanja-stok', [PenerimaanBarangKontroler::class, 'SimpanBelanja'])->name('kelola.pembelian.belanja-stok.simpan');

    Route::get('/faktur', [FakturPembelianKontroler::class, 'Daftar'])->name('kelola.pembelian.faktur.daftar');
    Route::get('/faktur/buat', [FakturPembelianKontroler::class, 'Buat'])->name('kelola.pembelian.faktur.buat');
    Route::post('/faktur', [FakturPembelianKontroler::class, 'Simpan'])->name('kelola.pembelian.faktur.simpan');
    Route::get('/faktur/{faktur}', [FakturPembelianKontroler::class, 'Detail'])->where('faktur', $ulid)->name('kelola.pembelian.faktur.detail');
    Route::post('/faktur/{faktur}/batalkan', [FakturPembelianKontroler::class, 'Batalkan'])->where('faktur', $ulid)->name('kelola.pembelian.faktur.batalkan');
    Route::get('/faktur/{faktur}/lampiran', [FakturPembelianKontroler::class, 'Lampiran'])->where('faktur', $ulid)->name('kelola.pembelian.faktur.lampiran');

    Route::get('/hutang', [HutangKontroler::class, 'Hutang'])->name('kelola.pembelian.hutang.daftar');
    Route::get('/pembayaran', [HutangKontroler::class, 'Daftar'])->name('kelola.pembelian.pembayaran.daftar');
    Route::get('/pembayaran/buat', [HutangKontroler::class, 'Buat'])->name('kelola.pembelian.pembayaran.buat');
    Route::post('/pembayaran', [HutangKontroler::class, 'Simpan'])->name('kelola.pembelian.pembayaran.simpan');
    Route::get('/pembayaran/{pembayaran}', [HutangKontroler::class, 'Detail'])->where('pembayaran', $ulid)->name('kelola.pembelian.pembayaran.detail');
    Route::post('/pembayaran/{pembayaran}/batalkan', [HutangKontroler::class, 'Batalkan'])->where('pembayaran', $ulid)->name('kelola.pembelian.pembayaran.batalkan');
    Route::get('/pembayaran/{pembayaran}/lampiran', [HutangKontroler::class, 'Lampiran'])->where('pembayaran', $ulid)->name('kelola.pembelian.pembayaran.lampiran');

    Route::get('/retur', [ReturPembelianKontroler::class, 'Daftar'])->name('kelola.pembelian.retur.daftar');
    Route::get('/retur/buat', [ReturPembelianKontroler::class, 'Buat'])->name('kelola.pembelian.retur.buat');
    Route::post('/retur', [ReturPembelianKontroler::class, 'Simpan'])->name('kelola.pembelian.retur.simpan');
    Route::get('/retur/{retur}', [ReturPembelianKontroler::class, 'Detail'])->where('retur', $ulid)->name('kelola.pembelian.retur.detail');
    Route::post('/retur/{retur}/batalkan', [ReturPembelianKontroler::class, 'Batalkan'])->where('retur', $ulid)->name('kelola.pembelian.retur.batalkan');
});

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::PembelianPoSetujui)])->prefix('pembelian')->group(function () use ($ulid): void {
    Route::post('/pesanan/{pesanan}/setujui', [PesananPembelianKontroler::class, 'Setujui'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.setujui');
    Route::post('/pesanan/{pesanan}/tolak', [PesananPembelianKontroler::class, 'Tolak'])->where('pesanan', $ulid)->name('kelola.pembelian.pesanan.tolak');
    Route::get('/pengaturan', [PengaturanPembelianKontroler::class, 'Tampilkan'])->name('kelola.pembelian.pengaturan');
    Route::put('/pengaturan', [PengaturanPembelianKontroler::class, 'Simpan'])->name('kelola.pembelian.pengaturan.simpan');
});

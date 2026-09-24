<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Aksi\ArsipkanProduk;
use App\Domain\Katalog\Aksi\BuatBarcodeInternal;
use App\Domain\Katalog\Aksi\HapusProduk;
use App\Domain\Katalog\Aksi\PulihkanProduk;
use App\Domain\Katalog\Aksi\SimpanProduk;
use App\Domain\Katalog\Data\DataSaringProduk;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\StatusProduk;
use App\Domain\Katalog\Kueri\DaftarProduk;
use App\Domain\Katalog\Kueri\DaftarSatuan;
use App\Domain\Katalog\Kueri\DetailProduk;
use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Kueri\PohonKategori;
use App\Domain\Katalog\Layanan\OpsiKelompokPajakKatalog;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Http\Permintaan\Kelola\Katalog\SimpanProdukPermintaan;
use App\Http\Respons\DaftarBerhalaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Produk F-03 (E.2–E.4, BR-03.1, BR-03.2): daftar, form buat/ubah, detail, arsip/pulihkan/hapus, barcode internal.
 * Harga awal satuan baru hanya dengan izin `produk.harga.ubah` (dikirim ke Aksi sebagai `bolehUbahHarga`).
 */
final class ProdukKontroler extends DasarKatalogKontroler
{
    public function Daftar(Request $permintaan, DaftarProduk $daftar, PohonKategori $pohon): Response
    {
        $saring = (array) $permintaan->input('saring', []);
        $uuidKategori = is_string($saring['Kategori'] ?? null) && $saring['Kategori'] !== '' ? $saring['Kategori'] : null;
        $jenis = is_string($saring['Jenis'] ?? null) ? JenisProduk::tryFrom($saring['Jenis']) : null;
        $statusTeks = is_string($saring['Status'] ?? null) ? $saring['Status'] : 'Aktif';
        $status = $statusTeks === 'Semua' ? null : (StatusProduk::tryFrom($statusTeks) ?? StatusProduk::Aktif);
        $urut = in_array($permintaan->query('urut'), ['Nama', '-DiubahPada', 'Sku'], true) ? (string) $permintaan->query('urut') : 'Nama';
        $idKategori = $uuidKategori === null ? null : Kategori::query()->where('Uuid', $uuidKategori)->value('Id');
        $kata = trim($permintaan->string('kata')->toString());

        $halaman = $daftar->Ambil(new DataSaringProduk($kata, is_int($idKategori) ? $idKategori : ($uuidKategori === null ? null : 0), $jenis, $status, $urut), max(1, $permintaan->integer('halaman', 1)));

        return Inertia::render('Kelola/Produk/Daftar', [
            'Produk' => DaftarBerhalaman::BuatDariData($halaman, $daftar->Petakan(array_values($halaman->items()))),
            'Saring' => [
                'Kata' => $kata,
                'Kategori' => is_int($idKategori) ? $uuidKategori : null,
                'Jenis' => $jenis?->value,
                'Status' => $status === null ? 'Semua' : $status->value,
                'Urut' => $urut,
            ],
            'Kategori' => $pohon->AmbilOpsi(),
            'Jenis' => JenisProduk::AmbilDaftarAturan(),
            'BatasSku' => $this->AmbilBatasSku(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Buat(DetailProduk $detail): Response
    {
        return $this->RenderForm('Buat', $detail->AmbilFormKosong(), null, false);
    }

    public function Simpan(SimpanProdukPermintaan $permintaan, SimpanProduk $simpan): RedirectResponse
    {
        $produk = $simpan->Jalankan(null, $permintaan->AmbilData(null, $this->CekIzin(IzinTenant::ProdukHargaUbah)));

        return redirect()->route('kelola.produk.detail', ['produk' => $produk->Uuid])->with('Kilat', "Produk {$produk->Nama} disimpan.");
    }

    public function Detail(string $produk, DetailProduk $detail, KepalaProduk $kepala): Response
    {
        $baris = $this->CariProduk($produk);

        return Inertia::render('Kelola/Produk/Detail', [
            'Kepala' => $kepala->Ambil($baris),
            'Produk' => $detail->Ambil($baris),
            'Varian' => $detail->AmbilVarian($baris),
            'BatasStok' => $detail->AmbilBatasStok($baris),
            'Riwayat' => $detail->AmbilRiwayat($baris),
            'Jenis' => JenisProduk::AmbilDaftarAturan(),
            'BatasSku' => $this->AmbilBatasSku(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Ubah(string $produk, DetailProduk $detail, KepalaProduk $kepala): Response
    {
        $baris = $this->CariProduk($produk);

        return $this->RenderForm('Ubah', $detail->AmbilForm($baris), $kepala->Ambil($baris), $detail->CekJenisTerkunci($baris));
    }

    public function Perbarui(string $produk, SimpanProdukPermintaan $permintaan, SimpanProduk $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $baris = $simpan->Jalankan($baris, $permintaan->AmbilData($baris, $this->CekIzin(IzinTenant::ProdukHargaUbah)));

        return redirect()->route('kelola.produk.detail', ['produk' => $baris->Uuid])->with('Kilat', "Produk {$baris->Nama} disimpan.");
    }

    public function Arsipkan(string $produk, ArsipkanProduk $arsipkan): RedirectResponse
    {
        $baris = $arsipkan->Jalankan($this->CariProduk($produk));

        return back()->with('Kilat', "Produk {$baris->Nama} diarsipkan. Produk tidak tampil di kasir, riwayatnya tetap tersimpan.");
    }

    public function Pulihkan(string $produk, PulihkanProduk $pulihkan): RedirectResponse
    {
        $baris = $pulihkan->Jalankan($this->CariProduk($produk));

        return back()->with('Kilat', "Produk {$baris->Nama} aktif kembali.");
    }

    public function Hapus(string $produk, HapusProduk $hapus): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $hapus->Jalankan($baris);

        return redirect()->route('kelola.produk.daftar')->with('Kilat', "Produk {$baris->Nama} dihapus.");
    }

    public function BuatBarcodeInternal(string $produk, string $produkSatuan, BuatBarcodeInternal $buat): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $satuan = ProdukSatuan::query()->where('IdProduk', $baris->Id)->where('Uuid', $produkSatuan)->firstOrFail();
        $barcode = $buat->Jalankan($satuan);

        return back()->with('Kilat', "Barcode {$barcode->Barcode} dibuat.");
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>|null  $kepala
     */
    private function RenderForm(string $mode, array $form, ?array $kepala, bool $jenisTerkunci): Response
    {
        return Inertia::render('Kelola/Produk/Form', [
            'Mode' => $mode,
            'Produk' => $form,
            'Kepala' => $kepala,
            'Kategori' => app(PohonKategori::class)->AmbilOpsi(),
            'Satuan' => app(DaftarSatuan::class)->AmbilOpsi(),
            'KelompokPajak' => app(OpsiKelompokPajakKatalog::class)->AmbilOpsiHalaman(),
            'Jenis' => JenisProduk::AmbilDaftarAturan(),
            'JenisTerkunci' => $jenisTerkunci,
            'BatasSku' => $this->AmbilBatasSku(),
            'Pengaturan' => $this->AmbilPengaturan(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    /**
     * @return array{Batas: int|null, Terpakai: int}
     */
    private function AmbilBatasSku(): array
    {
        return app(PastikanBatasPaket::class)->AmbilRingkasan($this->IdTenant(), 'BatasSku', app(PemakaianSku::class)->Hitung());
    }

    /**
     * @return array{HargaTermasukPajakOutlet: string, StokBolehMinus: bool}
     */
    private function AmbilPengaturan(): array
    {
        $profil = app(ProfilPajakOutlet::class)->Ambil((int) app(OutletUtama::class)->AmbilId());
        $pengaturan = app(ProfilTenant::class)->Ambil($this->IdTenant())['Pengaturan'];

        return [
            'HargaTermasukPajakOutlet' => $profil?->hargaTermasukPajak === true ? 'Ikut outlet: harga sudah termasuk pajak' : 'Ikut outlet: harga belum termasuk pajak',
            'StokBolehMinus' => ($pengaturan['StokBolehMinus'] ?? false) === true,
        ];
    }
}

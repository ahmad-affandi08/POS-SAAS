<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Aksi\SimpanPaketSesi;
use App\Domain\Katalog\Kueri\DaftarPaketSesi;
use App\Domain\Katalog\Model\PaketSesi;
use App\Domain\Pelanggan\Kueri\PengaturanSesiTenant;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master paket sesi (F-16d bagian 2, CRM-04): daftar (izin `produk.lihat`), tambah/ubah (izin `produk.kelola`, fitur
 * `pelanggan.paket-sesi`). Paket = produk Jasa yang dijual sebagai N sesi; harganya harga jual produk itu.
 */
final class PaketSesiKontroler extends DasarKatalogKontroler
{
    public function Daftar(Request $permintaan, DaftarPaketSesi $daftar, PengaturanSesiTenant $pengaturan): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPaketSesi::KOLOM_URUT, DaftarPaketSesi::URUT_BAWAAN, DaftarPaketSesi::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/PaketSesi/Daftar', 'PaketSesi', fn (): array => $daftar->AmbilTabel($tabel), fn (): array => [
            'Izin' => $this->AmbilIzinKatalog(),
            'FiturAktif' => $pengaturan->CekBerlaku(),
        ]);
    }

    public function Buat(DaftarPaketSesi $daftar, PengaturanSesiTenant $pengaturan): Response
    {
        return Inertia::render('Kelola/PaketSesi/Formulir', [
            'Paket' => null,
            'PilihanJasa' => $daftar->AmbilPilihanJasa(),
            'FiturAktif' => $pengaturan->CekBerlaku(),
        ]);
    }

    public function Ubah(string $paketSesi, DaftarPaketSesi $daftar, PengaturanSesiTenant $pengaturan): Response
    {
        return Inertia::render('Kelola/PaketSesi/Formulir', [
            'Paket' => $daftar->AmbilFormulir($this->CariPaket($paketSesi)),
            'PilihanJasa' => $daftar->AmbilPilihanJasa(),
            'FiturAktif' => $pengaturan->CekBerlaku(),
        ]);
    }

    public function Simpan(Request $permintaan, SimpanPaketSesi $simpan, PengaturanSesiTenant $pengaturan): RedirectResponse
    {
        $paket = $this->Jalankan($permintaan, null, $simpan, $pengaturan);

        return redirect()->route('kelola.paket-sesi.daftar')->with('Kilat', "Paket sesi {$paket->Produk->Nama} disimpan. Jual produknya di kasir untuk menambah sesi pelanggan.");
    }

    public function Perbarui(Request $permintaan, string $paketSesi, SimpanPaketSesi $simpan, PengaturanSesiTenant $pengaturan): RedirectResponse
    {
        $paket = $this->Jalankan($permintaan, $this->CariPaket($paketSesi), $simpan, $pengaturan);

        return redirect()->route('kelola.paket-sesi.daftar')->with('Kilat', "Paket sesi {$paket->Produk->Nama} diperbarui. Perubahan berlaku untuk penjualan berikutnya.");
    }

    private function Jalankan(Request $permintaan, ?PaketSesi $paket, SimpanPaketSesi $simpan, PengaturanSesiTenant $pengaturan): PaketSesi
    {
        if (! $pengaturan->CekBerlaku()) {
            throw new PelanggaranAturanBisnis('FiturTidakAktif', 'Paket usaha ini belum termasuk paket sesi. Tingkatkan paket langganan untuk memakainya.');
        }

        $valid = $permintaan->validate([
            'UuidProduk' => ['required', 'string', 'ulid'],
            'JumlahSesi' => ['required', 'integer', 'min:1', 'max:'.SimpanPaketSesi::MAKS_SESI],
            'MasaBerlakuHari' => ['nullable', 'integer', 'min:1', 'max:'.SimpanPaketSesi::MAKS_HARI],
            'SemuaProdukJasa' => ['required', 'boolean'],
            'ProdukBerlaku' => ['array', 'max:200'],
            'ProdukBerlaku.*' => ['string', 'ulid'],
            'Aktif' => ['required', 'boolean'],
        ], attributes: [
            'UuidProduk' => 'produk paket',
            'JumlahSesi' => 'jumlah sesi',
            'MasaBerlakuHari' => 'masa berlaku',
            'ProdukBerlaku' => 'layanan yang bisa ditukar',
        ]);

        $hasil = $simpan->Jalankan(
            $paket,
            (string) $valid['UuidProduk'],
            (int) $valid['JumlahSesi'],
            isset($valid['MasaBerlakuHari']) ? (int) $valid['MasaBerlakuHari'] : null,
            (bool) $valid['SemuaProdukJasa'],
            array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) ($valid['ProdukBerlaku'] ?? []))),
            (bool) $valid['Aktif'],
            $this->Pelaku()->Id,
        );
        $hasil->loadMissing('Produk:Id,Nama');

        return $hasil;
    }

    private function CariPaket(string $uuid): PaketSesi
    {
        return PaketSesi::query()->where('Uuid', $uuid)->firstOrFail();
    }
}

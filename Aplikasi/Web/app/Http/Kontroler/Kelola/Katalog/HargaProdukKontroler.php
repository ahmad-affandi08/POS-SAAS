<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Harga\Aksi\SimpanDaftarHarga;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaDaftarHarga;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaProduk;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Kueri\HargaProdukUntukHalaman;
use App\Domain\Katalog\Harga\Kueri\RiwayatHargaProduk;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Http\Permintaan\Kelola\Katalog\SimpanHargaDaftarHargaPermintaan;
use App\Http\Permintaan\Kelola\Katalog\SimpanHargaProdukPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Halaman harga produk F-03 (E.6): harga dasar & bertingkat per satuan, harga produk di tiap daftar harga, dan
 * riwayat harga (BR-03.3). Produk dicari lewat ULID di tenant aktif (tenant lain = 404).
 */
final class HargaProdukKontroler extends DasarKatalogKontroler
{
    public function Tampilkan(string $produk, Request $permintaan, KepalaProduk $kepala, HargaProdukUntukHalaman $harga, RiwayatHargaProduk $riwayat): Response|JsonResponse
    {
        $baris = $this->CariProduk($produk);
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), RiwayatHargaProduk::KOLOM_URUT, '-DibuatPada', RiwayatHargaProduk::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Produk/Harga', 'Riwayat', fn (): array => $riwayat->AmbilTabel($baris, $tabel), fn (): array => [
            'Kepala' => $kepala->Ambil($baris),
            ...$harga->Ambil($baris),
            'OpsiSumberRiwayat' => array_map(fn (SumberPerubahanHarga $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], SumberPerubahanHarga::cases()),
            'LabelHargaTermasukPajak' => $this->AmbilLabelHargaTermasukPajak($baris),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(string $produk, SimpanHargaProdukPermintaan $permintaan, SimpanHargaProduk $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $hasil = $simpan->Jalankan($baris, $permintaan->AmbilPerSatuan($baris), SumberPerubahanHarga::Manual);

        return back()->with('Kilat', $hasil->CekAdaPerubahan() ? "Harga {$baris->Nama} disimpan." : 'Tidak ada harga yang berubah.');
    }

    public function SimpanDaftarHarga(string $produk, string $daftarHarga, SimpanHargaDaftarHargaPermintaan $permintaan, SimpanHargaDaftarHarga $simpan): RedirectResponse
    {
        $baris = $this->CariProduk($produk);
        $daftar = DaftarHarga::query()->where('Uuid', $daftarHarga)->firstOrFail();
        SimpanDaftarHarga::PastikanAksesOutlet($daftar->IdOutlet, $this->IdOutletBoleh());
        $perSatuan = $permintaan->AmbilPerSatuanDariProduk($baris);

        try {
            $hasil = $simpan->Jalankan($daftar, $perSatuan, SumberPerubahanHarga::Manual);
        } catch (PelanggaranAturanBisnis $galat) {
            throw $permintaan->PetakanGalat($galat);
        }

        return back()->with('Kilat', $hasil->CekAdaPerubahan() ? "Harga {$baris->Nama} di {$daftar->Nama} disimpan." : 'Tidak ada harga yang berubah.');
    }

    private function AmbilLabelHargaTermasukPajak(Produk $produk): string
    {
        if ($produk->HargaTermasukPajak !== null) {
            return $produk->HargaTermasukPajak ? 'Harga sudah termasuk pajak' : 'Harga belum termasuk pajak';
        }

        $profil = app(ProfilPajakOutlet::class)->Ambil((int) app(OutletUtama::class)->AmbilId());

        return $profil?->hargaTermasukPajak === true ? 'Ikut outlet: harga sudah termasuk pajak' : 'Ikut outlet: harga belum termasuk pajak';
    }
}

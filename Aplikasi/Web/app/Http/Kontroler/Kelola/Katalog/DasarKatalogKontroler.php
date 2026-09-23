<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;

/**
 * Bantuan bersama kontroler katalog F-03 (semua tim): pencarian produk lewat ULID publik di dalam scope tenant aktif
 * (produk tenant lain atau yang sudah dihapus = 404), pemeriksaan izin pelaku, dan prop `Izin` (tipe FE
 * `IzinKatalog`).
 */
abstract class DasarKatalogKontroler extends DasarKelolaKontroler
{
    protected function CariProduk(string $uuid): Produk
    {
        return Produk::query()->where('Uuid', $uuid)->firstOrFail();
    }

    protected function CekIzin(IzinTenant $izin): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, $izin);
    }

    /**
     * @return array{Kelola: bool, UbahHarga: bool, KelolaPersediaan: bool, KelolaPajak: bool}
     */
    protected function AmbilIzinKatalog(): array
    {
        return [
            'Kelola' => $this->CekIzin(IzinTenant::ProdukKelola),
            'UbahHarga' => $this->CekIzin(IzinTenant::ProdukHargaUbah),
            'KelolaPersediaan' => $this->CekIzin(IzinTenant::PersediaanKelola),
            'KelolaPajak' => $this->CekIzin(IzinTenant::AkuntansiKelola),
        ];
    }
}

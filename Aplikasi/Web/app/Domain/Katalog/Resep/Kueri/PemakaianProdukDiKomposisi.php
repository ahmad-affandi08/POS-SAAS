<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Kueri;

use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\PaketProduk\Model\PaketProdukDetail;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;

/**
 * BR-03.2: produk yang dipakai di komposisi tidak bisa dihapus (hanya diarsipkan) dan jenisnya tidak bisa diubah.
 * Dipakai = bahan di versi resep **terbaru** produk yang belum dihapus, bahan sebuah pilihan, atau komponen paket
 * yang belum dihapus (F-03 C.4).
 */
final class PemakaianProdukDiKomposisi implements PemeriksaPemakaianProduk
{
    public function PeriksaPemakaian(int $idProduk): ?string
    {
        return $this->PeriksaResep($idProduk) ?? $this->PeriksaPilihan($idProduk) ?? $this->PeriksaPaket($idProduk);
    }

    private function PeriksaResep(int $idProduk): ?string
    {
        $idResep = ResepDetail::query()->where('IdProdukBahan', $idProduk)->distinct()->pluck('IdResep')->all();

        if ($idResep === []) {
            return null;
        }

        $kandidat = Resep::query()->whereKey($idResep)->whereIn('IdProduk', Produk::query()->select('Id'))->orderBy('Id')->get(['Id', 'IdProduk', 'Versi']);

        foreach ($kandidat as $resep) {
            $versiTerbaru = Resep::query()->where('IdProduk', $resep->IdProduk)->max('Versi');

            if ((int) $versiTerbaru === $resep->Versi) {
                $nama = Produk::query()->whereKey($resep->IdProduk)->value('Nama');

                return "dipakai sebagai bahan di resep {$nama} versi {$resep->Versi}";
            }
        }

        return null;
    }

    private function PeriksaPilihan(int $idProduk): ?string
    {
        $pilihan = Pilihan::query()->with('KelompokPilihan:Id,Nama')->where('IdProduk', $idProduk)->orderBy('Id')->first();

        return $pilihan === null ? null : "dipakai di pilihan {$pilihan->KelompokPilihan->Nama}/{$pilihan->Nama}";
    }

    private function PeriksaPaket(int $idProduk): ?string
    {
        $idPaket = PaketProdukDetail::query()->where('IdProdukKomponen', $idProduk)
            ->whereIn('IdProdukPaket', Produk::query()->select('Id'))
            ->orderBy('Id')
            ->value('IdProdukPaket');

        if ($idPaket === null) {
            return null;
        }

        return 'komponen paket '.Produk::query()->whereKey($idPaket)->value('Nama');
    }
}

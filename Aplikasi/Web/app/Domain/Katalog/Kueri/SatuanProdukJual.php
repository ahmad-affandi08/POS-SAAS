<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use Brick\Math\BigDecimal;

/**
 * Satuan **jual** sebuah produk beserta konversinya, untuk formulir yang menjual per satuan besar (grosir F-12:
 * dus, karton, lusin). Kembaran `SatuanProdukPembelian`, bedanya urutannya mendahulukan satuan jual bawaan —
 * di grosir yang dipilih pertama biasanya satuan besar, bukan satuan beli.
 */
final class SatuanProdukJual
{
    /**
     * @param  list<int>  $idProduk
     * @return array<int, list<array{Id: int, Uuid: string, IdProduk: int, Simbol: string, Nama: string, Konversi: string, DefaultJual: bool}>> kunci = IdProduk
     */
    public function AmbilUntukProduk(array $idProduk): array
    {
        if ($idProduk === []) {
            return [];
        }

        $baris = ProdukSatuan::query()->whereIn('IdProduk', array_values(array_unique($idProduk)))->get();
        $satuan = Satuan::query()->whereIn('Id', $baris->pluck('IdSatuan')->unique()->values()->all())->get()->keyBy('Id');
        $hasil = [];

        foreach ($baris as $b) {
            $s = $satuan->get($b->IdSatuan);
            $hasil[$b->IdProduk][] = [
                'Id' => $b->Id,
                'Uuid' => $b->Uuid,
                'IdProduk' => $b->IdProduk,
                'Simbol' => (string) $s?->Simbol,
                'Nama' => (string) $s?->Nama,
                'Konversi' => (string) $b->KonversiKeDasar,
                'DefaultJual' => $b->DefaultJual,
            ];
        }

        foreach ($hasil as $id => $daftar) {
            usort($daftar, fn (array $x, array $y): int => ($y['DefaultJual'] <=> $x['DefaultJual'])
                ?: (BigDecimal::of($y['Konversi'])->compareTo(BigDecimal::of($x['Konversi'])) ?: $x['Id'] <=> $y['Id']));
            $hasil[$id] = $daftar;
        }

        return $hasil;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use Brick\Math\BigDecimal;

/**
 * Satuan pembelian produk tenant aktif untuk domain Pembelian (F-04 fase 1): semua `ProdukSatuan` produk dengan
 * konversi ke satuan dasar, satuan beli bawaan (`DefaultBeli`) lebih dulu, lalu konversi terkecil. Tanpa Pembelian
 * membaca tabel Katalog (CLAUDE.md #14).
 *
 * @phpstan-type BarisSatuanPembelian array{Id: int, Uuid: string, IdProduk: int, Simbol: string, Nama: string, Konversi: string, DefaultBeli: bool}
 */
final class SatuanProdukPembelian
{
    /**
     * @param  list<int>  $idProduk
     * @return array<int, list<BarisSatuanPembelian>> kunci = IdProduk
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
                'DefaultBeli' => $b->DefaultBeli,
            ];
        }

        foreach ($hasil as $id => $daftar) {
            usort($daftar, fn (array $x, array $y): int => ($y['DefaultBeli'] <=> $x['DefaultBeli'])
                ?: (BigDecimal::of($x['Konversi'])->compareTo(BigDecimal::of($y['Konversi'])) ?: $x['Id'] <=> $y['Id']));
            $hasil[$id] = $daftar;
        }

        return $hasil;
    }

    /**
     * Satuan produk dari Uuid `ProdukSatuan`; null bila tidak ada atau milik produk lain.
     *
     * @return BarisSatuanPembelian|null
     */
    public function CariDariUuid(string $uuid, int $idProduk): ?array
    {
        foreach ($this->AmbilUntukProduk([$idProduk])[$idProduk] ?? [] as $baris) {
            if ($baris['Uuid'] === $uuid) {
                return $baris;
            }
        }

        return null;
    }
}

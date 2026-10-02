<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Model\SaldoStok;

/**
 * X7 Open API v1 (`GET /api/v1/stok`): saldo stok tenant aktif per (produk, lokasi stok) urut `Id`, halaman berbasis
 * kursor. Hanya jumlah (tersedia & dipesan); nilai persediaan/HPP tidak dikirim. Uuid produk & lokasi dipetakan oleh
 * pemanggil lewat kueri publik domain masing-masing.
 */
final class StokUntukApiPublik
{
    /**
     * @param  list<int>|null  $idGudang  null = semua lokasi stok
     * @return array{Data: list<array{IdProduk: int, IdGudang: int, JumlahTersedia: string, JumlahDipesan: string, DiubahPada: string|null}>, IdTerakhir: int|null}
     */
    public function Daftar(int $setelahId, int $batas, ?array $idGudang = null): array
    {
        $baris = SaldoStok::query()
            ->where('Id', '>', $setelahId)
            ->when($idGudang !== null, fn ($k) => $k->whereIn('IdGudang', $idGudang === [] ? [0] : $idGudang))
            ->orderBy('Id')
            ->limit($batas)
            ->get();

        return [
            'Data' => array_values($baris->map(fn (SaldoStok $s): array => [
                'IdProduk' => $s->IdProduk,
                'IdGudang' => $s->IdGudang,
                'JumlahTersedia' => (string) $s->JumlahTersedia,
                'JumlahDipesan' => (string) $s->JumlahDipesan,
                'DiubahPada' => $s->DiubahPada?->toIso8601ZuluString(),
            ])->all()),
            'IdTerakhir' => $baris->count() < $batas ? null : $baris->last()?->Id,
        ];
    }
}

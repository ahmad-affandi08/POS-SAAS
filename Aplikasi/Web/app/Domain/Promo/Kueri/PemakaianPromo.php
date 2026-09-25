<?php

declare(strict_types=1);

namespace App\Domain\Promo\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\PromoPemakaian;

/** Pemakaian promo (F-16c): rincian per penjualan (detail penjualan) dan ringkasan per promo (daftar promo). */
final class PemakaianPromo
{
    /**
     * @return list<array{Uuid: string, Kode: string, Nama: string, JumlahDiskon: string}>
     */
    public function AmbilPerPenjualan(int $idPenjualan): array
    {
        $pemakaian = PromoPemakaian::query()->where('IdPenjualan', $idPenjualan)->orderBy('Id')->get();
        $promo = Promo::query()->whereKey($pemakaian->pluck('IdPromo')->all())->get()->keyBy('Id');

        return array_values($pemakaian->map(fn (PromoPemakaian $p): array => [
            'Uuid' => $promo[$p->IdPromo]->Uuid ?? '',
            'Kode' => $promo[$p->IdPromo]->Kode ?? '',
            'Nama' => $promo[$p->IdPromo]->Nama ?? '',
            'JumlahDiskon' => (string) $p->JumlahDiskon,
        ])->all());
    }

    /**
     * @param  list<int>  $idPromo
     * @return array<int, array{JumlahPakai: int, TotalDiskon: string}>
     */
    public function AmbilRingkasan(array $idPromo): array
    {
        if ($idPromo === []) {
            return [];
        }

        $hasil = [];

        foreach (PromoPemakaian::query()->whereIn('IdPromo', $idPromo)->groupBy('IdPromo')->selectRaw('IdPromo, COUNT(*) AS Jumlah, SUM(JumlahDiskon) AS Total')->get() as $b) {
            $hasil[(int) $b->getAttribute('IdPromo')] = [
                'JumlahPakai' => (int) $b->getAttribute('Jumlah'),
                'TotalDiskon' => Uang::Dari((string) ($b->getAttribute('Total') ?? '0'))->KeString(),
            ];
        }

        return $hasil;
    }
}

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

    /**
     * Pemakaian promo oleh satu pelanggan (F-16c bagian 3, batas per pelanggan): per Uuid promo, jumlah pada tanggal
     * bisnis [tanggalBisnis] dan selama masa promo. Seperti kuota, penjualan yang di-void tetap terhitung.
     *
     * @param  int|null  $kecualiIdPenjualan  penjualan yang sedang diperiksa (bila sudah tercatat)
     * @return array<string, array{Hari: int, Promo: int}>
     */
    public function HitungPerPelanggan(int $idPelanggan, string $tanggalBisnis, ?int $kecualiIdPenjualan = null): array
    {
        return $this->HitungBanyakPelanggan([$idPelanggan], $tanggalBisnis, $kecualiIdPenjualan)[$idPelanggan] ?? [];
    }

    /**
     * Seperti [HitungPerPelanggan] untuk banyak pelanggan sekaligus (hasil cari pelanggan POS).
     *
     * @param  list<int>  $idPelanggan
     * @return array<int, array<string, array{Hari: int, Promo: int}>>
     */
    public function HitungBanyakPelanggan(array $idPelanggan, string $tanggalBisnis, ?int $kecualiIdPenjualan = null): array
    {
        if ($idPelanggan === []) {
            return [];
        }

        $baris = PromoPemakaian::query()
            ->whereIn('IdPelanggan', $idPelanggan)
            ->when($kecualiIdPenjualan !== null, fn ($k) => $k->where('IdPenjualan', '!=', $kecualiIdPenjualan))
            ->groupBy('IdPelanggan', 'IdPromo')
            ->selectRaw('IdPelanggan, IdPromo, COUNT(*) AS Semua, SUM(CASE WHEN TanggalBisnis = ? THEN 1 ELSE 0 END) AS Hari', [$tanggalBisnis])
            ->get();
        $uuid = Promo::query()->whereKey($baris->map(fn ($b): int => (int) $b->getAttribute('IdPromo'))->unique()->values()->all())->pluck('Uuid', 'Id');
        $hasil = [];

        foreach ($baris as $b) {
            $id = (int) $b->getAttribute('IdPromo');

            if (isset($uuid[$id])) {
                $hasil[(int) $b->getAttribute('IdPelanggan')][(string) $uuid[$id]] = ['Hari' => (int) $b->getAttribute('Hari'), 'Promo' => (int) $b->getAttribute('Semua')];
            }
        }

        return $hasil;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Respons;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Bentuk seragam daftar berhalaman untuk props Inertia: `{Data, HalamanSaatIni, HalamanTerakhir, Total}`.
 * Parameter query halaman memakai nama Indonesia `halaman` (D-06).
 */
final class DaftarBerhalaman
{
    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $halaman
     * @param  callable(TItem): array<string, mixed>  $petakan
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public static function Buat(LengthAwarePaginator $halaman, callable $petakan): array
    {
        return [
            'Data' => array_values(array_map($petakan, $halaman->items())),
            'HalamanSaatIni' => $halaman->currentPage(),
            'HalamanTerakhir' => $halaman->lastPage(),
            'Total' => $halaman->total(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tabel\Layanan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Menerapkan urut & paginasi `TabelData` ke kueri (PRD §17.4.3, D-16) dan menghasilkan kontrak
 * `{ Data, Meta: { Halaman, PerHalaman, Total, JumlahHalaman } }`. Saring & cari dikerjakan pemanggil (khas per halaman)
 * sebelum memanggil layanan ini. Kolom urut hanya dari peta daftar putih; pemutus seri `Id` membuat urutan stabil
 * antarhalaman. Halaman di luar jangkauan dijepit ke halaman terakhir.
 */
final class PenerapKueriTabel
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $kueri
     * @param  array<string, string|Closure(Builder<TModel>, bool): mixed>  $petaUrut  nama kolom tabel => kolom SQL atau penerap urut
     * @param  Closure(Collection<int, TModel>): list<array<string, mixed>>  $petakan
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public static function Terapkan(Builder $kueri, DataPermintaanTabel $permintaan, array $petaUrut, Closure $petakan, string $kolomPemutus = 'Id'): array
    {
        $total = (clone $kueri)->toBase()->getCountForPagination();
        $jumlahHalaman = max(1, intdiv($total + $permintaan->perHalaman - 1, $permintaan->perHalaman));
        $halaman = min($permintaan->halaman, $jumlahHalaman);

        foreach ($permintaan->urut as $urut) {
            $penerap = $petaUrut[$urut['Kolom']] ?? null;
            if ($penerap instanceof Closure) {
                $penerap($kueri, $urut['Turun']);
            } elseif (is_string($penerap)) {
                $kueri->orderBy($penerap, $urut['Turun'] ? 'desc' : 'asc');
            }
        }

        $model = $kueri->orderByDesc($kueri->getModel()->qualifyColumn($kolomPemutus))
            ->offset(($halaman - 1) * $permintaan->perHalaman)
            ->limit($permintaan->perHalaman)
            ->get();

        return [
            'Data' => $petakan($model),
            'Meta' => ['Halaman' => $halaman, 'PerHalaman' => $permintaan->perHalaman, 'Total' => $total, 'JumlahHalaman' => $jumlahHalaman],
        ];
    }

    /** Pola LIKE aman untuk kata cari (wildcard `%`/`_` dianggap huruf biasa). */
    public static function PolaCari(string $kata): string
    {
        return '%'.addcslashes($kata, '\\%_').'%';
    }

    /**
     * Mengubah paginator Laravel menjadi kontrak `TabelData` (untuk kueri yang sudah berhalaman sendiri).
     *
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $halaman
     * @param  Closure(list<TItem>): list<array<string, mixed>>  $petakan
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public static function DariPaginator(LengthAwarePaginator $halaman, Closure $petakan): array
    {
        return [
            'Data' => $petakan(array_values($halaman->items())),
            'Meta' => [
                'Halaman' => $halaman->currentPage(),
                'PerHalaman' => $halaman->perPage(),
                'Total' => $halaman->total(),
                'JumlahHalaman' => max(1, $halaman->lastPage()),
            ],
        ];
    }
}

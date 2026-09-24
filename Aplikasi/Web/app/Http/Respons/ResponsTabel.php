<?php

declare(strict_types=1);

namespace App\Http\Respons;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satu URL untuk halaman daftar dan datanya (PRD §17.4.3, D-16): kunjungan Inertia mendapat halaman beserta tabel
 * awal sebagai prop (tanpa kedip memuat), sedangkan `TabelData` (TanStack Query) meminta URL yang sama dengan
 * `Accept: application/json` untuk halaman/urut/saring berikutnya. Middleware izin & tenant berlaku sama untuk keduanya.
 */
final class ResponsTabel
{
    /** Permintaan data tabel dari TanStack Query, bukan kunjungan halaman Inertia. */
    public static function MintaData(Request $permintaan): bool
    {
        return $permintaan->expectsJson() && ! $permintaan->hasHeader('X-Inertia');
    }

    /**
     * @param  Closure(): array{Data: list<array<string, mixed>>, Meta: array<string, int>}  $tabel
     * @param  Closure(): array<string, mixed>|array<string, mixed>  $propsLain  hanya dihitung untuk kunjungan halaman
     */
    public static function Kirim(Request $permintaan, string $komponen, string $namaProp, Closure $tabel, Closure|array $propsLain = []): JsonResponse|Response
    {
        if (self::MintaData($permintaan)) {
            return response()->json($tabel());
        }

        $lain = $propsLain instanceof Closure ? $propsLain() : $propsLain;

        return Inertia::render($komponen, [$namaProp => $tabel(), ...$lain]);
    }

    /**
     * Mengubah paginator Laravel menjadi kontrak `TabelData`.
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

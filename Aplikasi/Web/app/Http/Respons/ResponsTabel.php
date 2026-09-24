<?php

declare(strict_types=1);

namespace App\Http\Respons;

use Closure;
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
     * @param  Closure(): array<string, mixed>  $tabel  `{Data, Meta}` (+ `Ringkasan` opsional)
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
}

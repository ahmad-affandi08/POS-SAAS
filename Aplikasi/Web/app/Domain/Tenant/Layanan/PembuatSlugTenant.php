<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Str;

/**
 * Slug tenant unik dari nama usaha (BR-00.2), dipakai untuk URL toko online `/{slugTenant}`. Kata yang bentrok
 * dengan rute sistem (§13.6) tidak boleh dipakai.
 */
final class PembuatSlugTenant
{
    public const PANJANG_MAKSIMAL = 60;

    public function Buat(string $namaUsaha): string
    {
        $dasar = Str::limit(Str::slug($namaUsaha), self::PANJANG_MAKSIMAL, '');
        $dasar = trim($dasar, '-');

        if ($dasar === '' || in_array($dasar, (array) config('tenant.SlugTerlarang'), true)) {
            $dasar = trim(($dasar === '' ? 'usaha' : $dasar).'-usaha', '-');
        }

        $slug = $dasar;
        $urutan = 2;

        while (Tenant::query()->where('Slug', $slug)->exists()) {
            $slug = "{$dasar}-{$urutan}";
            $urutan++;
        }

        return $slug;
    }
}

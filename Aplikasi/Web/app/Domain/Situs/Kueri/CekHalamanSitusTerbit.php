<?php

declare(strict_types=1);

namespace App\Domain\Situs\Kueri;

use App\Domain\Situs\Layanan\AturanSlugSitus;
use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Model\HalamanSitus;

/**
 * Apakah slug itu halaman situs pemasaran yang tampil ke publik (D-21)? Aturannya sama dengan
 * `PenyusunHalamanSitus::AmbilTerbit` tetapi tanpa menyusun isinya, supaya murah dipanggil saat pencocokan rute.
 */
final class CekHalamanSitusTerbit
{
    /** @var list<string>|null */
    private static ?array $slugBawaan = null;

    public function Ada(string $slug): bool
    {
        if ($slug === HalamanSitus::SLUG_BERANDA || AturanSlugSitus::Periksa($slug) !== null) {
            return false;
        }

        $halaman = HalamanSitus::query()->where('Slug', $slug)->first(['Id', 'Aktif', 'BagianTerbit']);

        if ($halaman === null) {
            self::$slugBawaan ??= array_map('strval', array_keys(KontenSitusBawaan::AmbilHalaman()));

            return in_array($slug, self::$slugBawaan, true);
        }

        return $halaman->Aktif && $halaman->CekTerbit();
    }
}

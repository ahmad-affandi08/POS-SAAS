<?php

declare(strict_types=1);

namespace App\Domain\Situs\Kueri;

use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Model\HalamanSitus;

/**
 * Halaman situs yang tampil publik untuk sitemap (D-21): terbit, aktif, dan `TampilDiSitemap`, ditambah halaman bawaan
 * yang belum dibuat di konsol.
 */
final class DaftarHalamanSitusPublik
{
    /**
     * @return list<array{Slug: string, DiubahPada: string|null}>
     */
    public function Ambil(): array
    {
        $baris = HalamanSitus::query()->orderBy('Id')->get(['Slug', 'Aktif', 'BagianTerbit', 'TampilDiSitemap', 'DiterbitkanPada']);
        $ada = $baris->pluck('Slug')->all();
        $hasil = [];

        foreach ($baris as $h) {
            if ($h->Aktif && $h->CekTerbit() && $h->TampilDiSitemap) {
                $hasil[] = ['Slug' => $h->Slug, 'DiubahPada' => $h->DiterbitkanPada?->toDateString()];
            }
        }

        foreach (array_keys(KontenSitusBawaan::AmbilHalaman()) as $slug) {
            if (! in_array($slug, $ada, true)) {
                $hasil[] = ['Slug' => $slug, 'DiubahPada' => null];
            }
        }

        return $hasil;
    }
}

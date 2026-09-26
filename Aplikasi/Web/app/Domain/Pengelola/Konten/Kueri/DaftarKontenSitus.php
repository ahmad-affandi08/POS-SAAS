<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Kueri;

use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;

/**
 * Daftar halaman & gambar situs pemasaran untuk konsol (D-21). Jumlah kecil (puluhan), jadi dimuat utuh untuk
 * TabelData mode lokal.
 */
final class DaftarKontenSitus
{
    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilHalaman(): array
    {
        return array_values(HalamanSitus::query()->orderByRaw("Slug <> 'beranda'")->orderBy('Slug')->get()
            ->map(fn (HalamanSitus $h): array => $this->PetakanHalaman($h))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function PetakanHalaman(HalamanSitus $h): array
    {
        return [
            'Uuid' => $h->Uuid,
            'Slug' => $h->Slug,
            'Judul' => $h->Judul,
            'Jalur' => $h->Slug === HalamanSitus::SLUG_BERANDA ? '/' : '/'.$h->Slug,
            'Terbit' => $h->CekTerbit(),
            'AdaPerubahan' => $h->CekAdaPerubahan(),
            'Aktif' => $h->Aktif,
            'DiterbitkanPada' => $h->DiterbitkanPada?->toIso8601ZuluString(),
            'DiubahPada' => $h->DiubahPada?->toIso8601ZuluString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilGambar(): array
    {
        return array_values(GambarSitus::query()->orderByDesc('Id')->limit(500)->get()
            ->map(fn (GambarSitus $g): array => [
                'Uuid' => $g->Uuid,
                'Url' => '/gambar-situs/'.$g->Uuid,
                'NamaBerkas' => $g->NamaBerkas,
                'TeksAlternatif' => $g->TeksAlternatif,
                'Lebar' => $g->Lebar,
                'Tinggi' => $g->Tinggi,
                'Ukuran' => $g->Ukuran,
                'DibuatPada' => $g->DibuatPada?->toIso8601ZuluString(),
            ])
            ->all());
    }
}

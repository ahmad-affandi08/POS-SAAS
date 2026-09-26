<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Model\HalamanSitus;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * D-21: membuat baris halaman bawaan situs pemasaran yang belum ada (langsung terbit dengan isi bawaan, sehingga situs
 * publik tidak berubah) agar bisa diedit di konsol. Idempoten; halaman yang sudah ada tidak disentuh.
 */
final class SiapkanHalamanSitusBawaan
{
    public function Jalankan(): void
    {
        $ada = HalamanSitus::query()->pluck('Slug')->all();

        foreach (KontenSitusBawaan::AmbilHalaman() as $slug => $isi) {
            if (in_array($slug, $ada, true)) {
                continue;
            }

            try {
                HalamanSitus::query()->create([
                    'Slug' => $slug,
                    'Judul' => $isi['Judul'],
                    'JudulSeo' => $isi['JudulSeo'],
                    'DeskripsiSeo' => $isi['DeskripsiSeo'],
                    'BagianDraf' => $isi['Bagian'],
                    'BagianTerbit' => $isi['Bagian'],
                    'JudulTerbit' => $isi['Judul'],
                    'JudulSeoTerbit' => $isi['JudulSeo'],
                    'DeskripsiSeoTerbit' => $isi['DeskripsiSeo'],
                    'DiterbitkanPada' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Dibuat permintaan lain bersamaan.
            }
        }
    }
}

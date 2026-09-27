<?php

declare(strict_types=1);

use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Layanan\SkemaBagianSitus;
use App\Domain\Situs\Layanan\ValidatorBagianSitus;

/*
 * Isi bawaan situs pemasaran (D-21) langsung dirender tanpa melewati editor konsol, jadi tidak ada yang
 * memvalidasinya saat dipasang. Test ini memastikan copy bawaan tetap lolos skema blok yang sama dengan
 * yang dipakai konsol, dan tetap memenuhi aturan susunan D-25.
 *
 * Semua halaman bawaan tidak memakai gambar, sehingga validator tidak menyentuh database.
 */

it('semua halaman bawaan lolos skema blok konsol', function (): void {
    $validator = new ValidatorBagianSitus;

    foreach (KontenSitusBawaan::AmbilHalaman() as $slug => $isi) {
        $hasil = $validator->Periksa($isi['Bagian']);

        expect($hasil)->toHaveCount(count($isi['Bagian']), "Blok halaman {$slug} berkurang setelah divalidasi.");
    }
});

it('setiap halaman bawaan punya judul dan deskripsi SEO', function (): void {
    foreach (KontenSitusBawaan::AmbilHalaman() as $slug => $isi) {
        expect($isi['JudulSeo'])->not->toBe('', "Halaman {$slug} tanpa judul SEO.");
        expect(mb_strlen($isi['DeskripsiSeo']))->toBeGreaterThan(50)->toBeLessThanOrEqual(320);
    }
});

it('tidak ada dua blok sejenis berurutan dengan bentuk sama (D-25)', function (): void {
    foreach (KontenSitusBawaan::AmbilHalaman() as $slug => $isi) {
        $sebelumnya = null;

        foreach ($isi['Bagian'] as $i => $blok) {
            $jenis = $blok['Jenis'];

            if ($sebelumnya !== null && $sebelumnya['Jenis'] === $jenis) {
                // Dua blok sejenis hanya boleh berdampingan bila bentuknya memang berbeda.
                expect($blok['TataLetak'] ?? null)
                    ->not->toBe($sebelumnya['TataLetak'] ?? null, "Halaman {$slug} blok {$i}: dua blok {$jenis} berurutan dengan bentuk sama.");
            }

            $sebelumnya = $blok;
        }
    }
});

it('halaman fitur tidak lagi berisi tiga grid keunggulan berturut-turut (D-25)', function (): void {
    $bagian = KontenSitusBawaan::AmbilHalaman()['fitur']['Bagian'];
    $jenis = array_column($bagian, 'Jenis');

    // Bentuk lama: Hero, Keunggulan, Keunggulan, Keunggulan, Cta.
    expect($jenis)->toBe(['Hero', 'Keunggulan', 'GambarTeks', 'Keunggulan', 'GambarTeks', 'Cta']);

    $tataLetak = array_values(array_filter(array_map(
        fn (array $b): ?string => $b['Jenis'] === 'Keunggulan' ? ($b['TataLetak'] ?? null) : null,
        $bagian,
    )));

    expect($tataLetak)->toBe(['Sorot', 'Daftar']);
});

it('copy bawaan tidak memakai frasa pemasaran kosong (D-25)', function (): void {
    // Frasa yang membuat halaman terasa dibuat mesin: klaim tanpa isi. Judul harus menyebut hal konkret.
    $terlarang = ['tanpa ribet', 'fitur lengkap', 'semua yang dibutuhkan', 'solusi terbaik', 'solusi lengkap'];
    $ditemukan = [];

    foreach (KontenSitusBawaan::AmbilHalaman() as $slug => $isi) {
        $teks = mb_strtolower(json_encode($isi, JSON_UNESCAPED_UNICODE) ?: '');

        foreach ($terlarang as $frasa) {
            if (str_contains($teks, $frasa)) {
                $ditemukan[] = "{$slug}: \"{$frasa}\"";
            }
        }
    }

    expect($ditemukan)->toBe([]);
});

it('ikon yang dipakai copy bawaan ada di daftar ikon yang diizinkan', function (): void {
    $tidakDikenal = [];

    foreach (KontenSitusBawaan::AmbilHalaman() as $slug => $isi) {
        foreach ($isi['Bagian'] as $blok) {
            foreach ($blok['Item'] ?? [] as $item) {
                $ikon = $item['Ikon'] ?? null;

                if (is_string($ikon) && ! in_array($ikon, SkemaBagianSitus::IKON, true)) {
                    $tidakDikenal[] = "{$slug}: {$ikon}";
                }
            }
        }
    }

    expect($tidakDikenal)->toBe([]);
});

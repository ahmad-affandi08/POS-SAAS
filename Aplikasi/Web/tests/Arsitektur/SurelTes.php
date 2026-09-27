<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Penjaga email PAYOU (D-26). File penjaga: kalau test ini gagal, perbaiki kodenya, bukan test-nya.
 *
 * Latar belakang: badan email tidak pernah diuji sama sekali. Test verifikasi email memeriksa properti
 * `$surel->tautan`, bukan hasil render templat. Akibatnya bug ini lolos ke produksi: badan `text/plain`
 * dirender Blade dengan `{{ }}`, yang meng-escape `&` menjadi `&amp;`. Tautan verifikasi berisi
 * `?expires=...&signature=...`, sehingga PHP membaca parameter kedua sebagai `amp;signature`, `signature`
 * terbaca kosong, dan setiap tautan verifikasi ditolak 403 "Invalid signature".
 */

/** Semua templat email (`resources/views/Surel`). */
function BerkasSurel(): Finder
{
    return Finder::create()->files()->in(resource_path('views/Surel'))->name('*.blade.php');
}

/** Templat badan teks: `Surel/{Grup}/{Nama}.blade.php`, di luar `Html/` dan `Komponen/`. */
function BerkasSurelTeks(): Finder
{
    return Finder::create()
        ->files()
        ->in(resource_path('views/Surel'))
        ->exclude(['Html', 'Komponen'])
        ->depth(1)
        ->name('*.blade.php');
}

/** Nilai hex token warna dari `Gaya/Aplikasi.css`, huruf kecil. */
function WarnaTokenSurel(): array
{
    preg_match_all('/--color-[a-z-]+:\s*(#[0-9a-fA-F]{3,8})/', (string) file_get_contents(resource_path('js/Gaya/Aplikasi.css')), $cocok);

    return array_map('strtolower', $cocok[1]);
}

it('setiap mailable mewarisi SurelDasar, jadi badan HTML & teks tidak bisa terpisah', function (): void {
    // `extends Mailable` langsung memungkinkan mailable mengirim satu bagian saja: HTML tanpa teks (lebih
    // sering dinilai spam) atau teks tanpa HTML (tidak bermerek).
    $pelanggar = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $berkas) {
        if (str_contains((string) $berkas->getContents(), 'extends Mailable') && $berkas->getFilename() !== 'SurelDasar.php') {
            $pelanggar[] = $berkas->getRelativePathname();
        }
    }

    expect($pelanggar)->toBe([]);
});

it('setiap badan teks punya pasangan HTML dan sebaliknya', function (): void {
    $teks = [];
    foreach (BerkasSurelTeks() as $berkas) {
        $teks[] = basename((string) $berkas->getRelativePath()).'/'.$berkas->getBasename('.blade.php');
    }

    $html = [];
    foreach (Finder::create()->files()->in(resource_path('views/Surel/Html'))->name('*.blade.php') as $berkas) {
        $html[] = basename((string) $berkas->getRelativePath()).'/'.$berkas->getBasename('.blade.php');
    }

    sort($teks);
    sort($html);

    expect($teks)->not->toBeEmpty()->and($html)->toBe($teks);
});

it('badan teks tidak meng-escape tautan, sehingga tanda tangan tautan tidak rusak', function (): void {
    // Regresi 403 "Invalid signature": di `text/plain`, `{{ $Tautan }}` mengubah `&` menjadi `&amp;`.
    $pelanggar = [];

    foreach (BerkasSurelTeks() as $berkas) {
        if (preg_match_all('/\{\{\s*\$[A-Za-z]*Tautan[A-Za-z]*\b/', (string) $berkas->getContents(), $cocok) > 0) {
            $pelanggar[] = $berkas->getRelativePathname().': '.implode(', ', $cocok[0]);
        }
    }

    expect($pelanggar)->toBe([]);
});

it('templat email tidak memuat gambar, jadi tidak ada piksel pelacak dan tidak bergantung gambar', function (): void {
    // Klien email memblokir gambar secara bawaan; email PAYOU harus utuh tanpa gambar. Sekaligus janji
    // "tanpa piksel pelacak" pada struk digital (K3).
    $pelanggar = [];

    foreach (BerkasSurel() as $berkas) {
        if (preg_match('/<img\b/i', (string) $berkas->getContents()) === 1) {
            $pelanggar[] = $berkas->getRelativePathname();
        }
    }

    expect($pelanggar)->toBe([]);
});

it('warna di templat email memakai nilai token, bukan hex lepas', function (): void {
    // Klien email tidak mendukung variabel CSS, jadi hex ditulis literal. Test ini yang menjaganya tetap
    // sama dengan token `Gaya/Aplikasi.css` (§17.6, D-15).
    $token = WarnaTokenSurel();
    $pelanggar = [];

    foreach (BerkasSurel() as $berkas) {
        // `(?<!&)` menyingkirkan entitas HTML numerik seperti `&#8199;` yang bukan warna.
        preg_match_all('/(?<!&)#[0-9a-fA-F]{3,8}\b/', (string) $berkas->getContents(), $cocok);

        foreach (array_unique(array_map('strtolower', $cocok[0])) as $hex) {
            if (! in_array($hex, $token, true)) {
                $pelanggar[] = $berkas->getRelativePathname().': '.$hex;
            }
        }
    }

    expect($token)->not->toBeEmpty()->and($pelanggar)->toBe([]);
});

it('setiap badan HTML memakai tata letak bersama', function (): void {
    $pelanggar = [];

    foreach (Finder::create()->files()->in(resource_path('views/Surel/Html'))->name('*.blade.php') as $berkas) {
        if (! str_contains((string) $berkas->getContents(), "@extends('Surel.TataLetak')")) {
            $pelanggar[] = $berkas->getRelativePathname();
        }
    }

    expect($pelanggar)->toBe([]);
});

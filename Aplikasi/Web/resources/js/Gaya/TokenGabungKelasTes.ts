import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

import { cn } from '@/Komponen/Ui/utils';

/*
 * Penjaga konfigurasi `cn` (D-28, §17.4.11). File penjaga: kalau test ini gagal, daftarkan tokennya di
 * `Komponen/Ui/utils.ts`, jangan melonggarkan test.
 *
 * Regresi yang dijaga: tailwind-merge tidak bisa membedakan `text-<ukuran>` dari `text-<warna>` kecuali diberi
 * tahu. Token skala situs (`sorotan`, `sorotan-besar`, `judul-bagian`, `pengantar`, beserta varian `-hp`) ditambah
 * untuk D-21/D-25 tetapi tidak pernah didaftarkan, jadi `cn` menganggapnya warna teks dan **membuang ukurannya**:
 * `cn('text-sorotan-besar-hp text-teks-utama sm:text-sorotan-besar')` keluar tanpa ukuran dasar sama sekali.
 * Akibatnya judul hero & judul bagian situs di HP turun ke ukuran warisan, sementara ukuran `sm:` di layar lebar
 * tetap berlaku — persis lolos dari uji 1280px dan hanya terlihat di 360px (D-16).
 */

/** Nama token ukuran teks di `Gaya/Aplikasi.css` (tanpa `--line-height` dan tanpa keluarga `shadow`). */
function TokenUkuranTeks(): string[] {
    const css = readFileSync('resources/js/Gaya/Aplikasi.css', 'utf8');
    const nama = [...css.matchAll(/^\s+--text-([a-z0-9-]+):/gm)].map((cocok) => cocok[1] ?? '');

    // `--text-judul--line-height` ikut tertangkap pola di atas; itu tinggi baris, bukan nama ukuran.
    return [...new Set(nama.filter((n) => n !== '' && !n.startsWith('shadow') && !n.endsWith('--line-height')))];
}

describe('Konfigurasi cn (tailwind-merge)', () => {
    it('setiap token ukuran teks tetap utuh saat digabung dengan warna teks', () => {
        const hilang: string[] = [];

        for (const token of TokenUkuranTeks()) {
            const hasil = cn(`text-${token} text-teks-utama`);

            if (!hasil.includes(`text-${token}`) || !hasil.includes('text-teks-utama')) {
                hilang.push(`text-${token} -> "${hasil}"`);
            }
        }

        expect(hilang).toEqual([]);
    });

    it('ukuran teks masih bisa ditimpa ukuran lain, jadi pendaftaran tidak melumpuhkan penggabungan', () => {
        expect(cn('text-isi', 'text-judul')).toBe('text-judul');
        expect(cn('text-sorotan', 'text-judul-bagian')).toBe('text-judul-bagian');
    });

    it('radius token dikenali sebagai radius, bukan hal lain', () => {
        expect(cn('rounded-panel', 'rounded-full')).toBe('rounded-full');
        expect(cn('rounded-panel shadow-none')).toBe('rounded-panel shadow-none');
    });
});

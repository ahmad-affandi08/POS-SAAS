import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { describe, expect, it } from 'vitest';

/*
 * Penjaga: layar tidak boleh ikut ter-zoom saat kontrol formulir difokuskan di ponsel.
 *
 * iOS Safari memperbesar halaman begitu `<input>` dengan `font-size` < 16px mendapat fokus. Token teks kita di
 * bawah itu (`--text-isi` 14px, `--text-label` 13px) dan beberapa komponen memasangnya langsung pada input, jadi
 * perbaikannya ada di `Gaya/Aplikasi.css`. Kalau test ini gagal, perbaiki CSS-nya, bukan test-nya.
 */

const akar = process.cwd();
const gaya = readFileSync(join(akar, 'resources/js/Gaya/Aplikasi.css'), 'utf8');

/** Kedalaman kurung kurawal pada posisi ini; 0 = di luar `@layer`, sehingga menang atas utilitas Tailwind. */
function KedalamanDi(isi: string, posisi: number): number {
    const awal = isi.slice(0, posisi);

    return (awal.match(/\{/g) ?? []).length - (awal.match(/\}/g) ?? []).length;
}

describe('Zoom otomatis di ponsel saat input difokuskan', () => {
    it('kontrol formulir dipaksa 16px pada perangkat sentuh', () => {
        const posisi = gaya.indexOf('@media (pointer: coarse)');
        expect(posisi).toBeGreaterThan(-1);

        const blok = gaya.slice(posisi, gaya.indexOf('}', gaya.indexOf('font-size: 16px', posisi)));
        for (const kontrol of ['input', 'textarea', 'select', 'contenteditable']) {
            expect(blok).toContain(kontrol);
        }
        expect(blok).toContain('font-size: 16px');
    });

    it('aturannya di luar @layer, kalau tidak utilitas Tailwind mengalahkannya', () => {
        // `.text-isi`/`.text-sm` ada di `@layer utilities`; aturan tanpa layer menang atas seluruh layer.
        expect(KedalamanDi(gaya, gaya.indexOf('@media (pointer: coarse)'))).toBe(0);
    });

    it('meta viewport tidak mematikan zoom manual (WCAG 1.4.4)', () => {
        // Jalan pintas yang dilarang: `maximum-scale=1` / `user-scalable=no` memang menghilangkan zoom saat
        // fokus, tetapi sekaligus mencegah pengguna memperbesar halaman sendiri.
        for (const berkas of ['Aplikasi.blade.php', 'Pengelola.blade.php', 'Situs.blade.php']) {
            const isi = readFileSync(join(akar, 'resources/views', berkas), 'utf8');

            expect(isi).toContain('width=device-width');
            expect(isi).not.toContain('maximum-scale');
            expect(isi).not.toContain('user-scalable');
        }
    });
});

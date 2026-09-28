import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga ruang aman tepi bawah (§17.4.4). File penjaga: kalau test ini gagal, perbaiki halamannya, bukan test-nya.
 *
 * Regresi yang dijaga: sembilan bilah aksi menempel di `bottom-0` tanpa satu pun memperhitungkan
 * `env(safe-area-inset-bottom)`. Di iPhone berlayar penuh, area itu milik indikator home, jadi tombol
 * "Simpan"/"Bayar" dan banner persetujuan cookie tertimpa indikator dan sulit diketuk.
 */

const AKAR = 'resources/js';
const BLADE = [
    'resources/views/Aplikasi.blade.php',
    'resources/views/Pengelola.blade.php',
    'resources/views/Situs.blade.php',
];

/** Semua berkas `.tsx` di luar `Komponen/Ui/` (keluaran CLI shadcn) dan di luar file penjaga. */
function BerkasHalaman(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((isi) => {
        const jalur = join(folder, isi.name);

        if (isi.isDirectory()) {
            return isi.name === 'Ui' ? [] : BerkasHalaman(jalur);
        }

        return jalur.endsWith('.tsx') && !/Tes\.tsx$/.test(isi.name) ? [jalur] : [];
    });
}

describe('Ruang aman tepi bawah', () => {
    it('setiap elemen yang menempel di bottom-0 memakai kelas tepi-bawah-aman', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasHalaman(AKAR)) {
            for (const cocok of readFileSync(jalur, 'utf8').matchAll(/className="([^"]*\bbottom-0\b[^"]*)"/g)) {
                const kelas = cocok[1] ?? '';

                // Hanya yang benar-benar menempel (fixed/sticky); `bottom-0` pada elemen absolut di dalam kartu
                // bukan tepi layar, jadi tidak butuh inset.
                if (!/\b(fixed|sticky)\b/.test(kelas)) {
                    continue;
                }

                if (!kelas.includes('tepi-bawah-aman')) {
                    pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: ${kelas.slice(0, 60)}`);
                }
            }
        }

        expect(pelanggar).toEqual([]);
    });

    it('kelas tepi-bawah-aman ada di Aplikasi.css dan memakai env(safe-area-inset-bottom)', () => {
        const css = readFileSync(join(AKAR, 'Gaya/Aplikasi.css'), 'utf8');
        const aturan = /\.tepi-bawah-aman\s*\{[^}]*env\(safe-area-inset-bottom/.test(css);

        expect(aturan, 'aturan .tepi-bawah-aman hilang atau tidak memakai env(safe-area-inset-bottom)').toBe(true);
    });

    it('meta viewport aplikasi memakai viewport-fit=cover, tanpa mematikan zoom', () => {
        // Tanpa `viewport-fit=cover`, `env(safe-area-inset-bottom)` selalu 0 di iOS dan aturan di atas tidak
        // berpengaruh apa pun. `maximum-scale`/`user-scalable` tetap dilarang (WCAG 1.4.4, lihat ZoomInputTes).
        for (const jalur of BLADE) {
            const isi = readFileSync(jalur, 'utf8');
            const meta = /<meta name="viewport" content="([^"]*)"/.exec(isi);

            expect(meta, `${jalur}: meta viewport tidak ditemukan`).not.toBeNull();
            expect(meta?.[1], jalur).toContain('viewport-fit=cover');
        }
    });
});

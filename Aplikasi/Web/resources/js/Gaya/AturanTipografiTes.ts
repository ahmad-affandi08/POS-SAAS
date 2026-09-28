import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga skala tipografi (§17.5). File penjaga: kalau test ini gagal, perbaiki kelasnya, bukan test-nya.
 *
 * Regresi yang dijaga: `text-judul-kecil` dan `text-body` dipakai di 3 halaman padahal tokennya tidak pernah ada.
 * Tailwind tidak mengeluh untuk kelas yang tidak dikenal — kelasnya hanya diam-diam tidak menghasilkan apa pun,
 * jadi headingnya turun ke ukuran warisan dan berbeda dari panel di halaman lain tanpa ada yang menyadarinya.
 *
 * Aturannya: di luar `Komponen/Ui/` (hasil CLI shadcn, dikecualikan), setiap `text-*` wajib berasal dari token
 * `@theme` — ukuran (`--text-*`) atau warna (`--color-*`) — kecuali utilitas yang memang bukan keduanya.
 */

const AKAR = 'resources/js';

/** Utilitas `text-*` bawaan Tailwind yang bukan ukuran & bukan warna, jadi memang tidak punya token. */
const utilitasBukanToken = new Set([
    'center',
    'left',
    'right',
    'justify',
    'start',
    'end',
    'inherit',
    'current',
    'transparent',
    'balance',
    'pretty',
    'wrap',
    'nowrap',
    'ellipsis',
    'clip',
]);

/** Nama token `@theme` yang ada: `--text-x` (ukuran) dan `--color-x` (warna). */
function TokenTeks(): Set<string> {
    const css = readFileSync(join(AKAR, 'Gaya/Aplikasi.css'), 'utf8');
    const ukuran = [...css.matchAll(/--text-([a-z0-9-]+?)(?:--line-height)?:/g)];
    const warna = [...css.matchAll(/--color-([a-z0-9-]+):/g)];

    return new Set([...ukuran, ...warna].flatMap((cocok) => (cocok[1] === undefined ? [] : [cocok[1]])));
}

/** Semua berkas `.ts`/`.tsx` di bawah folder ini, rekursif. */
function BerkasSumber(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((isi) => {
        const jalur = join(folder, isi.name);

        if (isi.isDirectory()) {
            return BerkasSumber(jalur);
        }

        return /\.tsx?$/.test(isi.name) ? [jalur] : [];
    });
}

describe('Skala tipografi', () => {
    it('setiap kelas text-* di luar Komponen/Ui berasal dari token @theme', () => {
        const token = TokenTeks();
        const pelanggar: string[] = [];

        for (const jalur of BerkasSumber(AKAR)) {
            // `Komponen/Ui` = keluaran CLI shadcn (dikecualikan), dan file penjaga memuat contoh yang salah
            // dengan sengaja sebagai bahan uji.
            if (jalur.includes('Komponen/Ui') || /Tes\.tsx?$/.test(jalur)) {
                continue;
            }

            for (const cocok of readFileSync(jalur, 'utf8').matchAll(/\btext-([a-z0-9-]+)/g)) {
                const nama = cocok[1];

                if (nama === undefined) {
                    continue;
                }

                if (!token.has(nama) && !utilitasBukanToken.has(nama)) {
                    pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: text-${nama}`);
                }
            }
        }

        expect([...new Set(pelanggar)]).toEqual([]);
    });

    it('token skala yang dipakai halaman memang ada di @theme', () => {
        const token = TokenTeks();

        // Skala §17.5 yang dipakai back-office; kalau salah satu hilang dari token, banyak halaman ikut rusak.
        for (const nama of ['tampilan', 'judul', 'subjudul', 'isi', 'label', 'keterangan']) {
            expect(token.has(nama), `token --text-${nama} hilang`).toBe(true);
        }
    });
});

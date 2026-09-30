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
 *
 * Lubang yang ditutup kemudian: pola nama token tidak pernah cocok dengan **nilai sembarang** `text-[11px]`, karena
 * setelah `text-` yang datang `[`. Empat ukuran di luar skala (`text-[11px]` & `text-[10px]` di `BagianTataLetak`)
 * lolos begitu saja sampai ada yang membacanya dengan mata. Nilai sembarang sekarang ditolak terpisah, dan §17.5
 * memang tidak menyisakan ruang untuknya: "maksimal 6 token di atas, tidak membuat ukuran baru di luar token",
 * pengecualiannya hanya judul hero situs pemasaran (D-25) yang tokennya pun tetap dideklarasikan di `@theme`.
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

            const isi = readFileSync(jalur, 'utf8');

            for (const cocok of isi.matchAll(/\btext-([a-z0-9-]+)/g)) {
                const nama = cocok[1];

                if (nama === undefined) {
                    continue;
                }

                if (!token.has(nama) && !utilitasBukanToken.has(nama)) {
                    pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: text-${nama}`);
                }
            }

            // Nilai sembarang `text-[...]`: ukuran di luar skala §17.5 maupun warna di luar palet, keduanya dilarang.
            for (const cocok of isi.matchAll(/\btext-\[[^\]]*\]/g)) {
                pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: ${cocok[0]}`);
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

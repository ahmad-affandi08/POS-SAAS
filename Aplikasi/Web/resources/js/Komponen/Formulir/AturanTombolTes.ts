import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga tombol aksi (§17.6.7, D-28). File penjaga: kalau test ini gagal, perbaiki halamannya, bukan test-nya.
 *
 * Regresi yang dijaga: dua API tombol hidup bersamaan. `Komponen/Formulir/Tombol` memberi spinner, label
 * "Memproses…", `aria-busy`, tinggi `h-8 pointer-coarse:h-11`, dan `text-label font-semibold`; `Button` shadcn
 * mentah tidak memberi satu pun dari itu dan memakai `text-sm font-medium`. Akibatnya tombol Simpan di sebagian
 * dialog hanya redup tanpa tanda proses, dan tingginya beda dengan tombol di halaman sebelahnya.
 *
 * `Button` mentah tetap sah untuk hal yang bukan aksi bisnis: tautan navigasi (`asChild`), pemicu Popover/Sheet,
 * chip saring bilah alat, dan tombol ikon. Yang dilarang adalah aksi bisnis.
 */

const AKAR = 'resources/js';
const FOOTER = ['DialogFooter', 'AlertDialogFooter', 'SheetFooter'];

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

/** Daerah isi tiap `<tag ...> ... </tag>`, memperhitungkan sarang. */
function DaerahTag(isi: string, tag: string): [number, number][] {
    const daerah: [number, number][] = [];
    const buka = new RegExp(`<${tag}(?:\\s[^>]*?)?>`, 'g');

    for (const awal of isi.matchAll(buka)) {
        const mulai = (awal.index ?? 0) + awal[0].length;
        let i = mulai;
        let dalam = 1;

        while (dalam > 0) {
            const lanjut = new RegExp(`<${tag}(?:\\s[^>]*?)?>|</${tag}>`, 'g');
            lanjut.lastIndex = i;
            const cocok = lanjut.exec(isi);

            if (cocok === null) {
                break;
            }

            dalam += cocok[0] === `</${tag}>` ? -1 : 1;
            i = cocok.index + cocok[0].length;

            if (dalam === 0) {
                daerah.push([mulai, i - `</${tag}>`.length]);
            }
        }
    }

    return daerah;
}

describe('Aturan tombol aksi', () => {
    it('tidak ada <Button> mentah bertipe submit: tombol kirim formulir memakai Tombol', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasHalaman(AKAR)) {
            const isi = readFileSync(jalur, 'utf8');

            for (const cocok of isi.matchAll(/<Button(\s[^>]*?)?>/g)) {
                if ((cocok[1] ?? '').includes('type="submit"')) {
                    pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: <Button type="submit">`);
                }
            }
        }

        expect(pelanggar).toEqual([]);
    });

    it('footer dialog/sheet hanya memuat Tombol atau tautan asChild', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasHalaman(AKAR)) {
            const isi = readFileSync(jalur, 'utf8');

            if (!isi.includes('<Button')) {
                continue;
            }

            const daerah = FOOTER.flatMap((tag) => DaerahTag(isi, tag));

            for (const cocok of isi.matchAll(/<Button(\s[^>]*?)?>/g)) {
                const mulai = cocok.index ?? 0;
                const atribut = cocok[1] ?? '';

                if (daerah.some(([a, b]) => a <= mulai && mulai <= b) && !/\basChild\b/.test(atribut)) {
                    pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: <Button> di footer dialog`);
                }
            }
        }

        expect(pelanggar).toEqual([]);
    });
});

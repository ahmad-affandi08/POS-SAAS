import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga isian teks panjang. File penjaga: kalau test ini gagal, perbaiki bidangnya, bukan test-nya.
 *
 * Regresi yang dijaga: `Textarea` shadcn memakai `field-sizing-content`, yang membuat tinggi kotak mengikuti isinya
 * dan **mengabaikan atribut `rows`**. Editor dokumen legal menulis `rows={20}` lalu tampil setinggi dua baris,
 * sehingga pengelola menyunting dokumen belasan pasal lewat celah sempit — dan tidak ada yang mengeluh karena
 * `rows` memang tidak error, hanya tidak berpengaruh. Setiap `Textarea` yang menyebut `rows` wajib ikut memakai
 * `field-sizing-fixed` supaya angka itu benar-benar berlaku.
 */

const AKAR = 'resources/js';

/** Semua berkas `.tsx` di luar `Komponen/Ui/` (keluaran CLI shadcn) dan di luar file penjaga. */
function BerkasSumber(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((isi) => {
        const jalur = join(folder, isi.name);

        if (isi.isDirectory()) {
            return isi.name === 'Ui' ? [] : BerkasSumber(jalur);
        }

        return jalur.endsWith('.tsx') && !/Tes\.tsx$/.test(isi.name) ? [jalur] : [];
    });
}

describe('Isian teks panjang', () => {
    it('setiap <Textarea> ber-rows juga memakai field-sizing-fixed, supaya rows berlaku', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasSumber(AKAR)) {
            const isi = readFileSync(jalur, 'utf8');

            for (const cocok of isi.matchAll(/<Textarea\b([\s\S]*?)\/>/g)) {
                const atribut = cocok[1] ?? '';

                if (/\brows=/.test(atribut) && !atribut.includes('field-sizing-fixed')) {
                    pelanggar.push(jalur.replace(`${AKAR}/`, ''));
                }
            }
        }

        expect([...new Set(pelanggar)]).toEqual([]);
    });
});

import { cleanup, render, screen } from '@testing-library/react';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

import BilahAksiForm from './BilahAksiForm';

/*
 * Penjaga bilah aksi formulir (D-28, §17.4.4 & §17.4.11). File penjaga: kalau test ini gagal, perbaiki halamannya.
 *
 * Regresi yang dijaga: §17.4.4 menyatakan tombol utama menempel di bawah pada layar sempit, tetapi pola itu
 * dirakit sendiri per halaman dengan enam kombinasi kelas berbeda — hanya 3 dari 17 formulir satu halaman yang
 * benar-benar menempel, dan tiga halaman memakai `justify-end` walau D-27 menetapkan tombol simpan di dalam
 * `<form>` rata kiri. Jadi di satu formulir "Simpan" selalu terlihat, di formulir lain pengguna harus menggulir
 * 800 baris ke bawah untuk menemukannya.
 */

const AKAR = 'resources/js/Halaman';
const BERKAS_FORMULIR = ['Form.tsx', 'Buat.tsx', 'Ubah.tsx', 'Formulir.tsx'];

/** Halaman formulir satu halaman di back-office tenant & konsol. */
function BerkasFormulir(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((isi) => {
        const jalur = join(folder, isi.name);

        if (isi.isDirectory()) {
            return BerkasFormulir(jalur);
        }

        return BERKAS_FORMULIR.includes(isi.name) ? [jalur] : [];
    });
}

afterEach(cleanup);

describe('Bilah aksi formulir', () => {
    it('setiap formulir satu halaman membungkus tombol kirimnya dengan BilahAksiForm', () => {
        const pelanggar: string[] = [];

        for (const folder of ['Kelola', 'Pengelola']) {
            for (const jalur of BerkasFormulir(join(AKAR, folder))) {
                const isi = readFileSync(jalur, 'utf8');
                const submit = isi.indexOf('type="submit"');

                if (!isi.includes('<form') || submit === -1) {
                    continue;
                }

                // Pembungkus terdekat sebelum tombol kirim harus BilahAksiForm, bukan `<div>` rakitan sendiri.
                const pembungkus = isi.slice(0, submit).lastIndexOf('<BilahAksiForm>');
                const divRakitan = isi.slice(0, submit).lastIndexOf('<div className="');

                if (pembungkus === -1 || divRakitan > pembungkus) {
                    pelanggar.push(jalur.replace(`${AKAR}/`, ''));
                }
            }
        }

        expect(pelanggar).toEqual([]);
    });

    it('menempel di bawah pada layar sempit, menyisakan ruang indikator home, dan rata kiri', () => {
        render(
            <BilahAksiForm>
                <button type="submit">Simpan produk</button>
            </BilahAksiForm>,
        );

        const bilah = screen.getByRole('button', { name: 'Simpan produk' }).parentElement;

        expect(bilah?.className).toContain('sticky bottom-0');
        // Tanpa ini, tombol tertimpa indikator home iPhone (§17.4.4).
        expect(bilah?.className).toContain('tepi-bawah-aman');
        // Dari 640px ke atas kembali jadi baris biasa, bukan bilah yang menutupi isi formulir.
        expect(bilah?.className).toContain('sm:static');
        // D-27: tombol simpan di dalam `<form>` rata kiri.
        expect(bilah?.className).not.toContain('justify-end');
    });
});

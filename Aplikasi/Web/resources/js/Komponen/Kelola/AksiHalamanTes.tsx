import { render, screen } from '@testing-library/react';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

import AksiHalaman from './AksiHalaman';

/*
 * Penjaga posisi tombol aksi halaman (D-27). File penjaga: kalau test ini gagal, perbaiki halamannya, bukan test-nya.
 *
 * Latar belakang: tiap halaman daftar merakit baris aksinya sendiri. Sebagian memakai `justify-between` sehingga
 * tombol "Tambah" di kanan, sebagian hanya `<div>` biasa sehingga tombolnya di kiri. Akibatnya posisi tombol
 * berpindah-pindah antar halaman dan pengguna harus mencarinya tiap kali.
 */

describe('AksiHalaman', () => {
    it('aksi rata kanan walau tanpa keterangan', () => {
        render(
            <AksiHalaman>
                <button type="button">Tambah sesuatu</button>
            </AksiHalaman>,
        );

        const tombol = screen.getByRole('button', { name: 'Tambah sesuatu' });
        // `ml-auto` (bukan `justify-between`) supaya tetap kanan meski keterangan tidak diisi.
        expect(tombol.parentElement?.className).toContain('ml-auto');
    });

    it('keterangan di kiri, aksi tetap di kanan', () => {
        render(
            <AksiHalaman keterangan={<p>Keterangan halaman</p>}>
                <button type="button">Tambah sesuatu</button>
            </AksiHalaman>,
        );

        const baris = screen.getByText('Keterangan halaman').parentElement;
        expect(baris?.firstElementChild?.textContent).toBe('Keterangan halaman');
        expect(baris?.lastElementChild?.className).toContain('ml-auto');
    });
});

/** Semua berkas `.tsx` di bawah folder ini, rekursif. */
function BerkasTsx(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((isi) => {
        const jalur = join(folder, isi.name);

        if (isi.isDirectory()) {
            return BerkasTsx(jalur);
        }

        return isi.name.endsWith('.tsx') ? [jalur] : [];
    });
}

describe('Baris aksi di halaman daftar', () => {
    it('tidak ada tombol Tambah/Buat yang dibungkus <div> telanjang (bikin rata kiri)', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasTsx('resources/js/Halaman/Kelola')) {
            const isi = readFileSync(jalur, 'utf8');
            const batasTabel = isi.indexOf('<TabelData');

            if (batasTabel === -1) {
                continue;
            }

            // Hanya baris aksi di atas tabel; tombol simpan di dalam <form> memang rata kiri dan bukan urusan ini.
            const kepala = isi.slice(0, batasTabel);
            const sebelumForm = kepala.includes('<form') ? kepala.slice(0, kepala.indexOf('<form')) : kepala;

            const bungkusVariabel = /<div>\{\w*[tT]ombol\w*\}<\/div>/.test(sebelumForm);

            // Isi setiap `<div>` telanjang diperiksa sampai `</div>`-nya saja. Tanpa batas itu, pencocokan
            // melompati elemen lain dan tautan "Kembali ke ..." (yang memang benar di kiri) ikut tertuduh.
            const bungkusLangsung = sebelumForm
                .split('<div>')
                .slice(1)
                .map((potongan) => potongan.split('</div>')[0] ?? '')
                .some((isiDiv) => /<(?:Button|Tombol)\b/.test(isiDiv) && />\s*(?:Tambah|Buat) /.test(isiDiv));

            if (bungkusVariabel || bungkusLangsung) {
                pelanggar.push(jalur.replace('resources/js/Halaman/Kelola/', ''));
            }
        }

        expect(pelanggar).toEqual([]);
    });

    it('tombol utama tidak dititipkan ke bilah alat tabel (aksiAlat)', () => {
        // D-27: bilah alat berisi kontrol yang bekerja pada isi tabel (cari, saring, atur kolom, ekspor). Tombol
        // utama di sana membuat tingginya berbeda dari halaman lain dan bisa terkubur saat bilahnya membungkus di HP.
        const pelanggar: string[] = [];

        for (const jalur of BerkasTsx('resources/js/Halaman/Kelola')) {
            if (readFileSync(jalur, 'utf8').includes('aksiAlat')) {
                pelanggar.push(jalur.replace('resources/js/Halaman/Kelola/', ''));
            }
        }

        expect(pelanggar).toEqual([]);
    });

    it('setiap halaman daftar dengan aksi utama memakai AksiHalaman', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasTsx('resources/js/Halaman/Kelola')) {
            const isi = readFileSync(jalur, 'utf8');
            const batasTabel = isi.indexOf('<TabelData');

            if (batasTabel === -1) {
                continue;
            }

            const kepala = isi.slice(0, batasTabel);
            const sebelumForm = kepala.includes('<form') ? kepala.slice(0, kepala.indexOf('<form')) : kepala;
            const adaAksi = /<(?:Button|Tombol)\b[\s\S]{0,200}?>\s*(?:Tambah|Buat|Mulai) /.test(sebelumForm);

            if (adaAksi && !isi.includes('AksiHalaman')) {
                pelanggar.push(jalur.replace('resources/js/Halaman/Kelola/', ''));
            }
        }

        expect(pelanggar).toEqual([]);
    });
});

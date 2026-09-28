import { cleanup, render, screen } from '@testing-library/react';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

import Panel from './Panel';

/*
 * Penjaga permukaan panel (D-28, §17.4.11). File penjaga: kalau test ini gagal, perbaiki halamannya.
 *
 * Regresi yang dijaga: pola panel ditulis ulang di tiga tempat dengan kelas berbeda — `PanelKatalog` (nama domain
 * padahal dipakai 40 berkas di luar katalog) plus fungsi `Panel` lokal di `Komponen/Laporan/DasborPemilik` dan
 * `Halaman/Pengelola/Tenant/Tampil` yang lupa `rounded-panel` & `shadow-none`. Di samping itu 45 `Card` mentah
 * memakai bawaan shadcn (`rounded-xl`, `shadow-sm`), jadi halaman berfungsi serupa punya radius & elevasi berbeda.
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

// Repo ini tidak memakai auto-cleanup Testing Library, jadi DOM test sebelumnya dibersihkan sendiri.
afterEach(cleanup);

describe('Permukaan panel', () => {
    it('setiap <Card> di luar Komponen/Ui memakai rounded-panel dan shadow-none', () => {
        const pelanggar: string[] = [];

        for (const jalur of BerkasSumber(AKAR)) {
            const isi = readFileSync(jalur, 'utf8');

            for (const cocok of isi.matchAll(/<Card\b[^>]*>/g)) {
                const tag = cocok[0];
                const literal = /className="([^"]*)"/.exec(tag);
                const ungkapan = /className=\{cn\('([^']*)'/.exec(tag);
                const kelas = `${literal?.[1] ?? ''} ${ungkapan?.[1] ?? ''}`;

                if (!kelas.includes('rounded-panel') || !kelas.includes('shadow-none')) {
                    pelanggar.push(`${jalur.replace(`${AKAR}/`, '')}: ${tag.replace(/\s+/g, ' ').slice(0, 70)}`);
                }
            }
        }

        expect(pelanggar).toEqual([]);
    });

    it('tidak ada komponen Panel tandingan di berkas lain', () => {
        // Tiga salinan pola yang sama itulah sebab radius & elevasinya menyimpang tanpa ada yang menyadarinya.
        const pelanggar = BerkasSumber(AKAR)
            .filter((jalur) => !jalur.endsWith('Komponen/Kelola/Panel.tsx'))
            .filter((jalur) => /\b(?:function|const)\s+Panel\b/.test(readFileSync(jalur, 'utf8')))
            .map((jalur) => jalur.replace(`${AKAR}/`, ''));

        expect(pelanggar).toEqual([]);
    });
});

describe('Panel', () => {
    it('panel berjudul jadi region bernama, dengan tingkat judul yang diminta', () => {
        render(
            <Panel
                judul="Tier & poin"
                keterangan="Dihitung dari total belanja."
                tingkat="h3"
                aksi={<button>Ubah</button>}
            >
                isi
            </Panel>,
        );

        expect(screen.getByRole('region', { name: 'Tier & poin' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Tier & poin', level: 3 })).toBeTruthy();
        expect(screen.getByText('Dihitung dari total belanja.')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Ubah' })).toBeTruthy();
    });

    it('tanpa judul hanya jadi permukaan: tidak ada region yang menambah kebisingan pembaca layar', () => {
        render(<Panel>isi saja</Panel>);

        expect(screen.queryByRole('region')).toBeNull();
        expect(screen.getByText('isi saja')).toBeTruthy();
    });
});

import { cleanup, render, screen, within } from '@testing-library/react';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

import { JejakHalaman } from './BagianTataLetak';

/*
 * Penjaga jejak halaman (D-27). File penjaga: kalau test ini gagal, perbaiki halamannya, bukan test-nya.
 *
 * Regresi yang dijaga: sejak jejak halaman dirender tata letak, halaman yang masih merender remah rotinya sendiri
 * menghasilkan **dua landmark navigasi** — dan `Outlet/Detail` bahkan memakai `aria-label` yang sama persis
 * ("Jejak halaman"), jadi pembaca layar menemukan dua navigasi bernama sama dan pengguna melihat dua jejak
 * bertumpuk. Halaman rincian menyambung jejak lewat prop `jejak`, bukan membuat remah roti kedua.
 */

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

// Repo ini tidak memakai auto-cleanup Testing Library, jadi DOM test sebelumnya harus dibersihkan sendiri.
afterEach(cleanup);

describe('JejakHalaman', () => {
    it('butir tanpa href jadi teks, butir ber-href jadi tautan', () => {
        render(
            <JejakHalaman
                jejak={[
                    { label: 'Kopi Nusantara' },
                    { label: 'Pengaturan', href: '/kelola/pengaturan' },
                    { label: 'Usaha' },
                ]}
            />,
        );

        const jejak = screen.getByRole('navigation', { name: 'Jejak halaman' });
        expect(within(jejak).getByRole('link', { name: 'Pengaturan' }).getAttribute('href')).toBe('/kelola/pengaturan');
        expect(within(jejak).queryByRole('link', { name: 'Kopi Nusantara' })).toBeNull();
        expect(within(jejak).getByText('Usaha')).toBeTruthy();
    });

    it('jejak kosong tidak merender apa pun', () => {
        render(<JejakHalaman jejak={[]} />);

        expect(screen.queryByRole('navigation', { name: 'Jejak halaman' })).toBeNull();
    });
});

describe('Halaman tidak merender remah roti sendiri', () => {
    it('tidak ada <Breadcrumb> di Halaman/; halaman rincian memakai prop `jejak`', () => {
        const pelanggar = BerkasTsx('resources/js/Halaman')
            .filter((jalur) => /<Breadcrumb\b/.test(readFileSync(jalur, 'utf8')))
            .map((jalur) => jalur.replace('resources/js/Halaman/', ''));

        expect(pelanggar).toEqual([]);
    });
});

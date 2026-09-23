import { cleanup, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { BuatLangkahContoh } from './DataUjiPanduan';
import IndikatorLangkah from './IndikatorLangkah';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
}));

const langkah = BuatLangkahContoh({ ProfilUsaha: 'Selesai', Sektor: 'Selesai', Pajak: 'Dilewati' });

describe('IndikatorLangkah (F-01, status dengan teks, bukan warna saja)', () => {
    afterEach(() => cleanup());

    it('menampilkan 6 langkah berurutan dengan status tertulis dan tautan ke tiap langkah', () => {
        render(<IndikatorLangkah langkah={langkah} aktif="Produk" />);

        const navigasi = screen.getByRole('navigation', { name: 'Langkah panduan awal' });
        const tautan = within(navigasi).getAllByRole('link');

        expect(tautan).toHaveLength(6);
        expect(tautan[0]?.textContent).toContain('1. Profil usaha');
        expect(tautan[0]?.textContent).toContain('Selesai');
        expect(tautan[2]?.textContent).toContain('Dilewati');
        expect(tautan[3]?.textContent).toContain('Belum');
        expect(tautan[5]?.getAttribute('href')).toBe('/kelola/panduan-awal/perangkat');
        expect(screen.getByText(/Langkah 4 dari 6/)).toBeTruthy();
        expect(screen.getByText(/2 dari 6 langkah selesai/)).toBeTruthy();
    });

    it('hanya langkah yang sedang dibuka yang memakai aria-current="step"', () => {
        render(<IndikatorLangkah langkah={langkah} aktif="Pajak" />);

        const saatIni = screen.getAllByRole('link').filter((elemen) => elemen.getAttribute('aria-current') === 'step');

        expect(saatIni).toHaveLength(1);
        expect(saatIni[0]?.textContent).toContain('3. Pajak');
        expect(saatIni[0]?.textContent).toContain('Sedang dibuka');
    });

    it('di halaman ringkasan tidak ada langkah aktif', () => {
        render(<IndikatorLangkah langkah={langkah} aktif={null} />);

        expect(document.querySelector('[aria-current]')).toBeNull();
        expect(screen.queryByText(/Langkah \d dari 6/)).toBeNull();
    });
});

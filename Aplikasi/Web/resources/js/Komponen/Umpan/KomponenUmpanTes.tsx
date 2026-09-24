import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import LabelStatus from './LabelStatus';
import Paginasi from './Paginasi';
import Pemberitahuan from './Pemberitahuan';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
}));

describe('komponen Umpan di atas shadcn/ui (API lama tetap)', () => {
    afterEach(() => cleanup());

    it('Pemberitahuan: bahaya & peringatan = alert, info & sukses = status; judul bawaan berupa teks', () => {
        render(
            <>
                <Pemberitahuan jenis="bahaya">Gagal menyimpan.</Pemberitahuan>
                <Pemberitahuan jenis="peringatan" judul="Tagihan belum dibayar">
                    Bayar sebelum 27 Sep.
                </Pemberitahuan>
                <Pemberitahuan jenis="sukses">Produk disimpan.</Pemberitahuan>
                <Pemberitahuan jenis="info">Info saja.</Pemberitahuan>
            </>,
        );

        const peringatan = screen.getAllByRole('alert');
        expect(peringatan).toHaveLength(2);
        expect(peringatan[0]?.textContent).toBe('GalatGagal menyimpan.');
        expect(peringatan[1]?.textContent).toContain('Tagihan belum dibayar');
        const status = screen.getAllByRole('status');
        expect(status.map((elemen) => elemen.textContent)).toEqual(['BerhasilProduk disimpan.', 'InfoInfo saja.']);
        expect(peringatan[0]?.getAttribute('data-slot')).toBe('alert');
    });

    it('LabelStatus: Badge berisi teks status', () => {
        render(<LabelStatus jenis="sukses" teks="Selesai" />);

        const label = screen.getByText('Selesai');
        expect(label.getAttribute('data-slot')).toBe('badge');
        expect(label.className).toContain('text-sukses');
    });

    it('Paginasi: tersembunyi bila satu halaman; tautan mempertahankan saringan dan label navigasi', () => {
        const { container, rerender: RenderUlang } = render(
            <Paginasi alamat="/kelola/produk" saring={{}} halamanSaatIni={1} halamanTerakhir={1} total={3} label="x" />,
        );
        expect(container.innerHTML).toBe('');

        RenderUlang(
            <Paginasi
                alamat="/kelola/produk"
                saring={{ kata: 'kopi' }}
                halamanSaatIni={2}
                halamanTerakhir={3}
                total={120}
                label="Halaman produk"
            />,
        );

        expect(screen.getByRole('navigation', { name: 'Halaman produk' })).toBeTruthy();
        expect(screen.getByText('Halaman 2 dari 3 · 120 entri')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Sebelumnya' }).getAttribute('href')).toBe(
            '/kelola/produk?kata=kopi&halaman=1',
        );
        expect(screen.getByRole('link', { name: 'Berikutnya' }).getAttribute('href')).toBe(
            '/kelola/produk?kata=kopi&halaman=3',
        );
    });
});

import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { ItemLangkahBerikutnya } from '@/Tipe/PanduanAwal';

import DaftarLangkahBerikutnya, { UrutkanLangkahBerikutnya } from './DaftarLangkahBerikutnya';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
}));

const daftar: ItemLangkahBerikutnya[] = [
    {
        Kunci: 'PanduanAwal',
        Judul: 'Selesaikan panduan awal',
        Keterangan: '4 dari 6 langkah selesai.',
        Tautan: '/kelola/panduan-awal',
        Selesai: false,
    },
    {
        Kunci: 'TambahProduk',
        Judul: 'Tambah produk',
        Keterangan: '12 produk sudah ada.',
        Tautan: '/kelola/panduan-awal/produk',
        Selesai: true,
    },
    {
        Kunci: 'AktifkanPerangkat',
        Judul: 'Aktifkan perangkat kasir',
        Keterangan: 'Belum ada perangkat aktif.',
        Tautan: '/kelola/perangkat',
        Selesai: false,
    },
];

describe('DaftarLangkahBerikutnya di Beranda (F-01 langkah 7)', () => {
    afterEach(() => cleanup());

    it('yang belum selesai tampil lebih dulu, urutan server dipertahankan', () => {
        expect(UrutkanLangkahBerikutnya(daftar).map((item) => item.Kunci)).toEqual([
            'PanduanAwal',
            'AktifkanPerangkat',
            'TambahProduk',
        ]);
    });

    it('setiap item berupa tautan dan statusnya tertulis', () => {
        render(<DaftarLangkahBerikutnya daftar={daftar} />);

        expect(screen.getByRole('heading', { name: 'Langkah berikutnya' })).toBeTruthy();
        expect(screen.getByText('1 dari 3 selesai')).toBeTruthy();

        const tautan = screen.getAllByRole('link');
        expect(tautan.map((elemen) => elemen.textContent)).toEqual([
            'Selesaikan panduan awal',
            'Aktifkan perangkat kasir',
            'Tambah produk',
        ]);
        expect(tautan[1]?.getAttribute('href')).toBe('/kelola/perangkat');
        expect(screen.getAllByText('Belum')).toHaveLength(2);
        expect(screen.getAllByText('Selesai')).toHaveLength(1);
    });

    it('daftar kosong: bagian disembunyikan', () => {
        const { container } = render(<DaftarLangkahBerikutnya daftar={[]} />);

        expect(container.innerHTML).toBe('');
    });
});

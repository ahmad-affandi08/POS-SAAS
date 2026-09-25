import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

import TataLetakAutentikasiPengelola from './TataLetakAutentikasiPengelola';
import TataLetakPengelola, { CekMenuPengelolaAktif } from './TataLetakPengelola';

let propsHalaman: PropsBersamaPengelola;
let urlHalaman = '/';

const tiruanRouter = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
    router: tiruanRouter,
    usePage: () => ({ props: propsHalaman, url: urlHalaman }),
}));

function BuatProps(izin: string[], tambahan: Partial<PropsBersamaPengelola> = {}): PropsBersamaPengelola {
    return {
        NamaAplikasi: 'Kasir',
        Lingkungan: 'Produksi',
        Kilat: null,
        Pengguna: { Uuid: '01J', Nama: 'Dewi Anggraini', Email: 'dewi@contoh.id', KodePeran: [], Izin: izin },
        PeringatanSuperAdmin: false,
        PeringatanIntegrasi: [],
        PeringatanOperasional: [],
        errors: {},
        ...tambahan,
    };
}

describe('TataLetakPengelola: menu sesuai izin, penanda lingkungan, banner (P-01, §13.8)', () => {
    afterEach(() => {
        cleanup();
        tiruanRouter.post.mockReset();
        document.cookie = 'sidebar_state=; path=/; max-age=0';
    });

    it('menu utama hanya berisi item yang diizinkan; menu aktif menurut segmen pertama URL', () => {
        propsHalaman = BuatProps(['katalog.lihat', 'audit.lihat']);
        urlHalaman = '/katalog/addon';
        render(
            <TataLetakPengelola judul="Katalog" aksi={<button type="button">Tambah paket</button>}>
                isi
            </TataLetakPengelola>,
        );

        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        expect(Array.from(utama.querySelectorAll('a')).map((a) => a.textContent)).toEqual([
            'Beranda',
            'Katalog',
            'Log audit',
        ]);
        expect(within(utama).getByRole('link', { name: 'Katalog' }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('button', { name: 'Tambah paket' })).toBeTruthy();
        expect(CekMenuPengelolaAktif('/', '/tenant')).toBe(false);
        expect(CekMenuPengelolaAktif('/referensi/tarif-pajak', '/referensi/wilayah')).toBe(true);
    });

    it('kepala sidebar memakai gradasi merek dan logo putih, bukan nama platform', () => {
        propsHalaman = BuatProps([]);
        const { container } = render(<TataLetakPengelola judul="Beranda">isi</TataLetakPengelola>);

        const kepala = container.querySelector('[data-slot="sidebar-header"]');
        expect(kepala?.className).toContain('bg-linear-to-br');
        expect(kepala?.className).toContain('from-brand-gelap');
        expect(kepala?.className).toContain('to-brand');
        expect(kepala?.querySelector('img[src*="LogoHorizontalPutih.png"]')).toBeTruthy();
        expect(kepala?.querySelector('img[src*="IkonMerekPutih.png"]')).toBeTruthy();
        expect(kepala?.textContent).not.toContain('Kasir · Pengelola');
    });

    it('penanda lingkungan selalu tampil dan banner integrasi & operasional ditampilkan', () => {
        propsHalaman = BuatProps([], {
            PeringatanSuperAdmin: true,
            PeringatanIntegrasi: ['Kunci Midtrans kedaluwarsa.'],
            PeringatanOperasional: ['Antrean tertunda 20 menit.'],
        });
        urlHalaman = '/';
        render(<TataLetakPengelola judul="Halo">isi</TataLetakPengelola>);

        expect(screen.getByText('Produksi: perubahan berdampak ke tenant sungguhan')).toBeTruthy();
        expect(screen.getByText('Super Admin aktif kurang dari 2')).toBeTruthy();
        expect(screen.getByText('Status integrasi')).toBeTruthy();
        expect(screen.getByText('Kunci Midtrans kedaluwarsa.')).toBeTruthy();
        expect(screen.getByText('Masalah operasional')).toBeTruthy();
        expect(screen.getAllByRole('alert')).toHaveLength(3);
    });

    it('Keluar dari menu akun mem-POST /keluar', () => {
        propsHalaman = BuatProps([]);
        render(<TataLetakPengelola judul="Halo">isi</TataLetakPengelola>);

        fireEvent.keyDown(screen.getByRole('button', { name: 'Menu akun Dewi Anggraini' }), { key: 'Enter' });
        fireEvent.click(screen.getByRole('menuitem', { name: 'Keluar' }));

        expect(tiruanRouter.post).toHaveBeenCalledWith('/keluar');
    });

    it('tata letak autentikasi Pengelola: penanda lingkungan, judul, kilat, dan Toaster', () => {
        propsHalaman = BuatProps([], { Lingkungan: 'Staging', Kilat: 'Tautan terkirim.' });
        render(<TataLetakAutentikasiPengelola judul="Masuk">isi formulir</TataLetakAutentikasiPengelola>);

        expect(screen.getByText('Staging: data uji')).toBeTruthy();
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Masuk');
        expect(screen.getByRole('status').textContent).toContain('Tautan terkirim.');
        expect(screen.getByRole('region', { name: /^Notifikasi/ })).toBeTruthy();
    });
});

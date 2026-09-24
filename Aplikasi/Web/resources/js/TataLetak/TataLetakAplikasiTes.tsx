import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PropsBersamaAplikasi, TenantAktif } from '@/Tipe/Aplikasi';

import TataLetakAplikasi, { CariMenuProdukAktif } from './TataLetakAplikasi';

let propsHalaman: PropsBersamaAplikasi;
let urlHalaman = '/kelola';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
    router: { post: vi.fn() },
    usePage: () => ({ props: propsHalaman, url: urlHalaman }),
}));

function BuatProps(tenant: Partial<TenantAktif> | null, izin: string[], pemilik = false): PropsBersamaAplikasi {
    return {
        NamaAplikasi: 'Kasir',
        Kilat: null,
        Pengguna: { Uuid: '01J', Nama: 'Rina Wulandari', Email: 'rina@kopinusantara.id', EmailTerverifikasi: true },
        TenantAktif:
            tenant === null
                ? null
                : {
                      Nama: 'Kopi Nusantara',
                      StatusLangganan: 'Aktif',
                      PeriodeSelesai: null,
                      BatasTenggangPada: null,
                      ...tenant,
                  },
        PengumumanLegal: [],
        Akses: tenant === null ? null : { Pemilik: pemilik, Izin: izin },
        errors: {},
    };
}

describe('TataLetakAplikasi: menu berbasis izin & banner langganan (F-00, §19.1)', () => {
    beforeEach(() => {
        propsHalaman = BuatProps({}, []);
        urlHalaman = '/kelola';
    });

    afterEach(() => {
        cleanup();
    });

    it('Pemilik melihat menu Langganan dan Bantuan; Keamanan akun selalu ada', () => {
        propsHalaman = BuatProps({}, [], true);
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);

        expect(screen.getByRole('link', { name: 'Langganan' }).getAttribute('href')).toBe('/kelola/langganan');
        expect(screen.getByRole('link', { name: 'Bantuan' }).getAttribute('href')).toBe('/kelola/bantuan');
        expect(screen.getByRole('link', { name: 'Keamanan akun' })).toBeTruthy();
    });

    it('Kasir tanpa izin tidak melihat menu Langganan maupun Bantuan', () => {
        propsHalaman = BuatProps({}, ['produk.lihat', 'penjualan.buat']);
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);

        expect(screen.queryByRole('link', { name: 'Langganan' })).toBeNull();
        expect(screen.queryByRole('link', { name: 'Bantuan' })).toBeNull();
        expect(screen.getByRole('link', { name: 'Keamanan akun' })).toBeTruthy();
    });

    it('tanpa tenant aktif, menu tenant disembunyikan', () => {
        propsHalaman = BuatProps(null, []);
        render(<TataLetakAplikasi judul="Keamanan akun">isi</TataLetakAplikasi>);

        expect(screen.queryByRole('navigation', { name: 'Menu utama' })).toBeNull();
        expect(screen.getByRole('link', { name: 'Keamanan akun' })).toBeTruthy();
    });

    it('Tertunggak: banner menyebut batas masa tenggang dan tautan bayar untuk pemegang langganan.kelola', () => {
        propsHalaman = BuatProps(
            {
                StatusLangganan: 'Tertunggak',
                PeriodeSelesai: '2026-09-19T17:00:00Z',
                BatasTenggangPada: '2026-09-26T17:00:00Z',
            },
            [],
            true,
        );
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);

        expect(screen.getByText('Tagihan langganan belum dibayar')).toBeTruthy();
        expect(screen.getByText(/sampai 27 Sep 2026/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Bayar tagihan di menu Langganan' })).toBeTruthy();
    });

    it('Ditangguhkan: anggota tanpa langganan.kelola diminta menghubungi pemilik usaha', () => {
        propsHalaman = BuatProps({ StatusLangganan: 'Ditangguhkan' }, ['outlet.lihat']);
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);

        expect(screen.getByText('Langganan ditangguhkan')).toBeTruthy();
        expect(screen.getByText('Hubungi pemilik usaha untuk membayar tagihan.')).toBeTruthy();
        expect(screen.queryByRole('link', { name: 'Bayar tagihan di menu Langganan' })).toBeNull();
    });

    it('F-03: grup menu Produk tampil sebagai sub-menu; Impor produk hanya untuk produk.kelola', () => {
        propsHalaman = BuatProps({}, ['produk.lihat']);
        urlHalaman = '/kelola/kategori';
        render(<TataLetakAplikasi judul="Kategori">isi</TataLetakAplikasi>);

        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        expect(utama.querySelector('a[href="/kelola/produk"]')?.getAttribute('aria-current')).toBe('page');
        const sub = screen.getByRole('navigation', { name: 'Menu produk' });
        expect(Array.from(sub.querySelectorAll('a')).map((a) => a.textContent)).toEqual([
            'Produk',
            'Kategori',
            'Satuan',
            'Daftar harga',
            'Pilihan (modifier)',
            'Kelompok pajak',
        ]);
        expect(sub.querySelector('a[aria-current="page"]')?.textContent).toBe('Kategori');
    });

    it('F-03: sub-menu memilih awalan terpanjang dan tersembunyi di luar grup Produk', () => {
        expect(CariMenuProdukAktif('/kelola/produk/impor/01J9?x=1')).toBe('/kelola/produk/impor');
        expect(CariMenuProdukAktif('/kelola/produk/01J9/harga')).toBe('/kelola/produk');
        expect(CariMenuProdukAktif('/kelola/produk?kata=kopi')).toBe('/kelola/produk');
        expect(CariMenuProdukAktif('/kelola/produk-lain')).toBeNull();
        expect(CariMenuProdukAktif('/kelola/outlet')).toBeNull();

        propsHalaman = BuatProps({}, ['produk.lihat']);
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);
        expect(screen.queryByRole('navigation', { name: 'Menu produk' })).toBeNull();
    });
});

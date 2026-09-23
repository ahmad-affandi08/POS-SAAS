import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PropsBersamaAplikasi, TenantAktif } from '@/Tipe/Aplikasi';

import TataLetakAplikasi from './TataLetakAplikasi';

let propsHalaman: PropsBersamaAplikasi;

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
    router: { post: vi.fn() },
    usePage: () => ({ props: propsHalaman, url: '/kelola' }),
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
});

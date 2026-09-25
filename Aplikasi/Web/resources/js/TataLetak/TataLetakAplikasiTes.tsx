import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PropsBersamaAplikasi, TenantAktif } from '@/Tipe/Aplikasi';

import TataLetakAplikasi, { CariMenuProdukAktif, CekMenuAktif, SaringMenuTerlihat } from './TataLetakAplikasi';

let propsHalaman: PropsBersamaAplikasi;
let urlHalaman = '/kelola';

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
        tiruanRouter.post.mockReset();
        // Status ciut bilah samping disimpan SidebarProvider di cookie; jangan bocor ke test berikutnya.
        document.cookie = 'sidebar_state=; path=/; max-age=0';
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
            'Stasiun dapur',
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

    it('F-05a: grup Persediaan untuk persediaan.lihat; impor & pengaturan tersembunyi tanpa izinnya', () => {
        propsHalaman = BuatProps({}, ['persediaan.lihat']);
        urlHalaman = '/kelola/persediaan/stok-awal/01J9ZC5V7Q8R2T4W6Y8A0B2C4D';
        render(<TataLetakAplikasi judul="Stok awal">isi</TataLetakAplikasi>);

        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        const induk = within(utama).getByRole('link', { name: 'Persediaan' });
        expect(induk.getAttribute('href')).toBe('/kelola/persediaan/saldo');
        expect(induk.getAttribute('aria-current')).toBe('page');
        const sub = screen.getByRole('navigation', { name: 'Menu persediaan' });
        expect(Array.from(sub.querySelectorAll('a')).map((a) => a.textContent)).toEqual([
            'Saldo stok',
            'Kartu stok',
            'Stok awal',
            'Transfer stok',
            'Stok opname',
            'Penyesuaian stok',
        ]);
        expect(sub.querySelector('a[aria-current="page"]')?.textContent).toBe('Stok awal');
        expect(within(utama).queryByRole('link', { name: 'Akuntansi' })).toBeNull();
    });

    it('F-05a: persediaan.kelola + akuntansi.kelola melihat Impor stok awal & Pengaturan; awalan terpanjang aktif', () => {
        propsHalaman = BuatProps({}, ['persediaan.lihat', 'persediaan.kelola', 'akuntansi.kelola']);
        urlHalaman = '/kelola/persediaan/stok-awal/impor/01J9ZC5V7Q8R2T4W6Y8A0B2C4D';
        render(<TataLetakAplikasi judul="Impor stok awal">isi</TataLetakAplikasi>);

        const sub = screen.getByRole('navigation', { name: 'Menu persediaan' });
        expect(Array.from(sub.querySelectorAll('a')).map((a) => a.getAttribute('href'))).toEqual([
            '/kelola/persediaan/saldo',
            '/kelola/persediaan/kartu-stok',
            '/kelola/persediaan/stok-awal',
            '/kelola/persediaan/transfer',
            '/kelola/persediaan/opname',
            '/kelola/persediaan/penyesuaian',
            '/kelola/persediaan/stok-awal/impor',
            '/kelola/persediaan/pengaturan',
        ]);
        expect(sub.querySelector('a[aria-current="page"]')?.textContent).toBe('Impor stok awal');
        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        const aktif = Array.from(utama.querySelectorAll(':scope a[aria-current="page"]')).map((a) => a.textContent);
        expect(aktif).toEqual(['Persediaan', 'Impor stok awal']);
    });

    it('F-05a: tanpa persediaan.lihat tetapi dengan akuntansi.kelola, grup Persediaan menuju Pengaturan persediaan', () => {
        propsHalaman = BuatProps({}, ['akuntansi.kelola']);
        urlHalaman = '/kelola/persediaan/pengaturan';
        render(<TataLetakAplikasi judul="Pengaturan persediaan">isi</TataLetakAplikasi>);

        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        expect(within(utama).getByRole('link', { name: 'Persediaan' }).getAttribute('href')).toBe(
            '/kelola/persediaan/pengaturan',
        );
        const sub = screen.getByRole('navigation', { name: 'Menu persediaan' });
        expect(Array.from(sub.querySelectorAll('a')).map((a) => a.textContent)).toEqual(['Pengaturan persediaan']);
    });

    it('F-05a: menu Akuntansi › Jurnal hanya untuk laporan.keuangan.lihat', () => {
        propsHalaman = BuatProps({}, ['laporan.keuangan.lihat']);
        urlHalaman = '/kelola/akuntansi/jurnal/01J9ZC5V7Q8R2T4W6Y8A0B2C4D';
        render(<TataLetakAplikasi judul="Jurnal">isi</TataLetakAplikasi>);

        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        expect(within(utama).getByRole('link', { name: 'Akuntansi' }).getAttribute('href')).toBe(
            '/kelola/akuntansi/jurnal',
        );
        expect(within(utama).queryByRole('link', { name: 'Persediaan' })).toBeNull();
        const sub = screen.getByRole('navigation', { name: 'Menu akuntansi' });
        expect(within(sub).getByRole('link', { name: 'Jurnal' }).getAttribute('aria-current')).toBe('page');
        cleanup();

        propsHalaman = BuatProps({}, ['produk.lihat', 'persediaan.lihat']);
        urlHalaman = '/kelola';
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);
        expect(screen.queryByRole('link', { name: 'Akuntansi' })).toBeNull();
        expect(screen.queryByRole('navigation', { name: 'Menu persediaan' })).toBeNull();
    });

    it('F-05a: Pemilik melihat seluruh menu persediaan dan jurnal; sub-menu aktif memakai awalan terpanjang', () => {
        expect(SaringMenuTerlihat({ Pemilik: true, Izin: [] }).map(({ menu }) => menu.label)).toEqual([
            'Beranda',
            'Outlet',
            'Produk',
            'Persediaan',
            // F-04: pembelian & hutang pemasok.
            'Pembelian',
            // F-07b: daftar penjualan dari POS.
            'Penjualan',
            // F-16a: data pelanggan.
            'Pelanggan',
            'Shift & kas',
            'Akuntansi',
            // F-14a: laporan penjualan, pajak, stok.
            'Laporan',
            'Perangkat',
            'Pengguna & peran',
            'Log audit',
            'Langganan',
            'Bantuan',
        ]);
        expect(SaringMenuTerlihat({ Pemilik: false, Izin: [] }).map(({ menu }) => menu.label)).toEqual(['Beranda']);
        expect(CekMenuAktif('/kelola/persediaan/saldo', '/kelola/persediaan/kartu-stok?produk=01J9')).toBe(true);
        expect(CekMenuAktif('/kelola/persediaan/saldo', '/kelola/produk')).toBe(false);
        expect(CekMenuAktif('/kelola/akuntansi/jurnal', '/kelola/akuntansi/jurnal/01J9')).toBe(true);
        // F-04: grup Pembelian tampil untuk pembelian.kelola; pengaturan pembelian hanya untuk pembelian.po.setujui.
        expect(
            SaringMenuTerlihat({ Pemilik: false, Izin: ['pembelian.kelola'] }).map(({ menu }) => menu.label),
        ).toEqual(['Beranda', 'Pembelian']);
        expect(CekMenuAktif('/kelola/pembelian/pesanan', '/kelola/pembelian/faktur/01J9')).toBe(true);
        // F-06: grup "Shift & kas" hanya tampil bila ada sub-menu yang boleh dibuka; F-07b: menu Penjualan ikut
        // izin laporan.penjualan.lihat.
        expect(
            SaringMenuTerlihat({ Pemilik: false, Izin: ['laporan.penjualan.lihat'] }).map(({ menu }) => menu.label),
        ).toEqual(['Beranda', 'Penjualan', 'Shift & kas', 'Laporan']);
        expect(CekMenuAktif('/kelola/penjualan', '/kelola/penjualan/01J9')).toBe(true);
        // F-16a: menu Pelanggan ikut izin pelanggan.lihat.
        expect(SaringMenuTerlihat({ Pemilik: false, Izin: ['pelanggan.lihat'] }).map(({ menu }) => menu.label)).toEqual(
            ['Beranda', 'Pelanggan'],
        );
        // F-14a: grup "Laporan" hanya berisi laporan yang boleh dibuka; tautannya = sub-menu pertama yang boleh.
        const laporanStok = SaringMenuTerlihat({ Pemilik: false, Izin: ['persediaan.lihat'] }).find(
            ({ menu }) => menu.label === 'Laporan',
        );
        expect(laporanStok?.sub.map((m) => m.label)).toEqual(['Laporan stok']);
        expect(CekMenuAktif('/kelola/laporan/penjualan', '/kelola/laporan/pajak?dari=2026-10-01')).toBe(true);
    });

    it('Pengguna & peran tetap aktif di /kelola/peran; aria-current hanya pada satu menu utama', () => {
        propsHalaman = BuatProps({}, ['pengguna.lihat', 'outlet.lihat']);
        urlHalaman = '/kelola/peran/01J9';
        render(<TataLetakAplikasi judul="Peran">isi</TataLetakAplikasi>);

        const utama = screen.getByRole('navigation', { name: 'Menu utama' });
        const aktif = Array.from(utama.querySelectorAll('a[aria-current="page"]'));
        expect(aktif.map((a) => a.textContent)).toEqual(['Pengguna & peran']);
        expect(CekMenuAktif('/kelola', '/kelola/outlet')).toBe(false);
        expect(CekMenuAktif('/kelola/outlet', '/kelola/outlet/01J9')).toBe(true);
    });

    it('bilah samping bisa diciutkan lewat tombol di bilah atas; remah roti menyebut usaha dan halaman', () => {
        propsHalaman = BuatProps({}, ['outlet.lihat']);
        urlHalaman = '/kelola/outlet';
        const { container } = render(<TataLetakAplikasi judul="Outlet">isi</TataLetakAplikasi>);

        const sidebar = container.querySelector('[data-slot="sidebar"]');
        expect(sidebar?.getAttribute('data-state')).toBe('expanded');

        fireEvent.click(screen.getByRole('button', { name: 'Buka atau tutup menu samping' }));

        expect(sidebar?.getAttribute('data-state')).toBe('collapsed');
        expect(sidebar?.getAttribute('data-collapsible')).toBe('icon');

        const remah = screen.getByRole('navigation', { name: 'Remah roti' });
        expect(within(remah).getByText('Kopi Nusantara')).toBeTruthy();
        expect(within(remah).getByText('Outlet').getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Outlet');
        expect(screen.getByRole('main').textContent).toContain('isi');
    });

    it('menu akun (DropdownMenu) menampilkan nama & email, lalu Keluar mem-POST /keluar', () => {
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);

        const pemicu = screen.getByRole('button', { name: 'Menu akun Rina Wulandari' });
        expect(screen.queryByRole('menuitem', { name: 'Keluar' })).toBeNull();

        fireEvent.keyDown(pemicu, { key: 'Enter' });

        const menu = screen.getByRole('menu');
        expect(within(menu).getByText('rina@kopinusantara.id')).toBeTruthy();

        fireEvent.click(within(menu).getByRole('menuitem', { name: 'Keluar' }));

        expect(tiruanRouter.post).toHaveBeenCalledWith('/keluar');
    });

    it('memasang Toaster dengan label Bahasa Indonesia', () => {
        render(<TataLetakAplikasi judul="Beranda">isi</TataLetakAplikasi>);

        expect(screen.getByRole('region', { name: /^Notifikasi/ })).toBeTruthy();
    });
});

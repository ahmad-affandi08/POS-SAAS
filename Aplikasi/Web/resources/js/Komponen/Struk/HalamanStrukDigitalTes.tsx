import { cleanup, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import HalamanStrukDigital, { type StrukDigital } from '@/Halaman/Publik/StrukDigital';
import { RenderUji } from '@/Komponen/Katalog/TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const struk: StrukDigital = {
    NamaUsaha: 'Kopi Senja Solo',
    TeksKepala: ['@kopisenja'],
    NamaOutlet: 'Outlet Solo Baru',
    Alamat: 'Jl. Slamet Riyadi 10',
    Npwp: null,
    Nomor: 'INV/SLO/260920/K01-0001',
    Waktu: '2026-09-20T03:15:00Z',
    NamaKasir: 'Rina Wulandari',
    NamaPelanggan: null,
    Dibatalkan: false,
    Baris: [
        {
            NamaProduk: 'Kopi Susu Gula Aren',
            Pilihan: ['Less sugar'],
            Jumlah: '2.000',
            HargaSatuan: '18000.00',
            Diskon: '2000.00',
            Total: '36000.00',
        },
    ],
    Subtotal: '36000.00',
    TotalDiskon: '2000.00',
    BiayaLayanan: '0.00',
    Pajak: [{ Kode: 'PB1', Tarif: '10.00', Jumlah: '3400.00' }],
    Pembulatan: '0.00',
    TotalAkhir: '37400.00',
    Pembayaran: [{ NamaMetode: 'Tunai', Jumlah: '50000.00' }],
    Kembalian: '12600.00',
    TotalRetur: null,
    CatatanKaki: null,
    TeksPenutup: null,
};

describe('Struk digital publik (POS-11)', () => {
    afterEach(() => cleanup());

    it('menampilkan isi struk: nomor, baris, pajak, total, kembalian, penutup bawaan', () => {
        RenderUji(<HalamanStrukDigital Struk={struk} />);
        expect(screen.getByRole('article', { name: 'Struk INV/SLO/260920/K01-0001' })).toBeTruthy();
        expect(screen.getByText('Kopi Susu Gula Aren')).toBeTruthy();
        expect(screen.getByText('+ Less sugar')).toBeTruthy();
        expect(screen.getByText('PB1 10%')).toBeTruthy();
        expect(screen.getByText('Rp 37.400')).toBeTruthy();
        expect(screen.getByText('Rp 12.600')).toBeTruthy();
        expect(screen.getByText('Terima kasih atas kunjungan Anda')).toBeTruthy();
        expect(screen.queryByText('TRANSAKSI DIBATALKAN')).toBeNull();
    });

    it('void ditandai dan struk tidak dikenal menampilkan keadaan belum tersedia', () => {
        RenderUji(<HalamanStrukDigital Struk={{ ...struk, Dibatalkan: true, TotalRetur: null }} />);
        expect(screen.getByRole('status').textContent).toBe('TRANSAKSI DIBATALKAN');
        cleanup();
        RenderUji(<HalamanStrukDigital Struk={null} />);
        expect(screen.getByRole('heading', { name: 'Struk belum tersedia' })).toBeTruthy();
    });
});

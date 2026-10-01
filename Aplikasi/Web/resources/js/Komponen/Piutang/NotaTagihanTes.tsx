import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import HalamanNotaTagihan from '@/Halaman/Kelola/Piutang/NotaTagihan';
import type { PropsNotaTagihan } from '@/Tipe/Pelanggan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const dasar: PropsNotaTagihan = {
    Baris: [
        {
            Nomor: 'JL-KSR1-2610-0001',
            TanggalBisnis: '2026-10-01',
            JatuhTempo: '2026-10-15',
            Jumlah: '77000.00',
            Dibayar: '27000.00',
            Sisa: '50000.00',
            HariLewat: 6,
        },
        {
            Nomor: 'JL-KSR1-2610-0002',
            TanggalBisnis: '2026-10-20',
            JatuhTempo: '2026-11-03',
            Jumlah: '1385000.00',
            Dibayar: '0.00',
            Sisa: '1385000.00',
            HariLewat: 0,
        },
    ],
    TotalSisa: '1435000.00',
    TotalLewat: '50000.00',
    Tanggal: '2026-10-21',
    Pelanggan: { Nama: 'Toko Makmur Jaya Abadi Sentosa', NoHp: '0813-5555-0001', Alamat: 'Pasar Gede Blok B-12, Solo' },
    Usaha: { Nama: 'Grosir Sembako Sumber Rejeki', Npwp: null },
};

describe('Nota tagihan pelanggan (F-12, v3.37)', () => {
    afterEach(() => cleanup());

    it('rincian piutang, tanda lewat jatuh tempo, total, dan peringatan', () => {
        render(<HalamanNotaTagihan {...dasar} />);
        expect(screen.getByRole('region', { name: 'Rincian tagihan' })).toBeTruthy();
        expect(screen.getByText('Lewat 6 hari')).toBeTruthy();
        expect(screen.getByText('Rp 1.435.000')).toBeTruthy();
        expect(screen.getByText(/sudah lewat jatuh tempo/)).toBeTruthy();
        expect(screen.getAllByText('Toko Makmur Jaya Abadi Sentosa').length).toBeGreaterThan(0);
    });

    it('tanpa piutang: pesan lunas, tanpa tabel & peringatan', () => {
        render(<HalamanNotaTagihan {...dasar} Baris={[]} TotalSisa="0.00" TotalLewat="0.00" />);
        expect(screen.getByText(/Tidak ada tagihan yang belum lunas/)).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'Rincian tagihan' })).toBeNull();
        expect(screen.queryByText(/sudah lewat jatuh tempo/)).toBeNull();
    });
});

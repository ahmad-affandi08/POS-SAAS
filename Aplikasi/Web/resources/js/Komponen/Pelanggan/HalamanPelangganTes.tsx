import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarPelanggan from '@/Halaman/Kelola/Pelanggan/Daftar';
import HalamanDetailPelanggan from '@/Halaman/Kelola/Pelanggan/Detail';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';
import type { BarisPelanggan } from '@/Tipe/Pelanggan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Ani: BarisPelanggan = {
    Uuid: '01K5PELANGGAN0000000000001',
    Nama: 'Ani Rahmawati Kusumaningtyas Wulandari Putri',
    NoHp: '0812-3456-7890',
    Email: 'ani@contoh.id',
    TanggalLahir: '1990-05-17',
    Alamat: 'Jl. Slamet Riyadi No. 10, Solo',
    Tag: ['Langganan', 'Reseller'],
    Catatan: null,
    SetujuPemasaran: true,
    Status: 'Aktif',
    DibuatPada: '2026-09-20T02:00:00Z',
    JumlahTransaksi: 12,
    TotalBelanja: '12500000.00',
    TerakhirPada: '2026-09-24T05:30:00Z',
};

describe('Halaman pelanggan (F-16a)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/pelanggan');
        window.history.replaceState({}, '', '/kelola/pelanggan');
    });
    afterEach(() => cleanup());

    it('daftar: ringkasan belanja & tag tampil; tambah hanya untuk pelanggan.kelola; formulir mengirim POST', () => {
        RenderUji(
            <HalamanDaftarPelanggan
                Pelanggan={BuatHasilTabel([Ani])}
                OpsiTag={['Langganan', 'Reseller']}
                Izin={{ Kelola: true, LihatPenjualan: true }}
            />,
        );
        expect(screen.getAllByText('Rp 12.500.000').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Langganan · Reseller').length).toBeGreaterThan(0);

        const tambah = screen.getAllByRole('button', { name: 'Tambah pelanggan' })[0];
        expect(tambah).toBeDefined();
        fireEvent.click(tambah as HTMLElement);
        fireEvent.change(screen.getByLabelText('Nama pelanggan'), { target: { value: 'Budi Santoso' } });
        fireEvent.change(screen.getByLabelText('No. HP/WA'), { target: { value: '0813 1111 2222' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pelanggan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/pelanggan',
            expect.objectContaining({ Nama: 'Budi Santoso', NoHp: '0813 1111 2222', TanggalLahir: null, Tag: [] }),
            expect.anything(),
        );

        cleanup();
        RenderUji(
            <HalamanDaftarPelanggan
                Pelanggan={BuatHasilTabel([])}
                OpsiTag={[]}
                Izin={{ Kelola: false, LihatPenjualan: false }}
            />,
        );
        expect(screen.queryByRole('button', { name: 'Tambah pelanggan' })).toBeNull();
    });

    it('detail: profil, ringkasan, riwayat bertaut ke penjualan; arsipkan mengirim POST', () => {
        RenderUji(
            <HalamanDetailPelanggan
                Pelanggan={Ani}
                Riwayat={[
                    {
                        Uuid: '01K5JUAL000000000000000001',
                        Nomor: 'INV/SLB/260924/POS-001-0007',
                        TanggalBisnis: '2026-09-24',
                        DibuatPada: '2026-09-24T05:30:00Z',
                        Status: 'Lunas',
                        TotalAkhir: '1250000.00',
                    },
                ]}
                Izin={{ Kelola: true, LihatPenjualan: true }}
            />,
        );
        expect(screen.getByText('0812-3456-7890')).toBeTruthy();
        expect(screen.getByText('Setuju menerima')).toBeTruthy();
        expect(screen.getAllByRole('link', { name: 'INV/SLB/260924/POS-001-0007' })[0]?.getAttribute('href')).toBe(
            '/kelola/penjualan/01K5JUAL000000000000000001',
        );
        fireEvent.click(screen.getByRole('button', { name: 'Arsipkan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(`/kelola/pelanggan/${Ani.Uuid}/arsipkan`, {}, expect.anything());
    });
});

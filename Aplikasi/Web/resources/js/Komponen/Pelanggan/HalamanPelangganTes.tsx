import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarPelanggan from '@/Halaman/Kelola/Pelanggan/Daftar';
import HalamanDetailPelanggan from '@/Halaman/Kelola/Pelanggan/Detail';
import HalamanPengaturanLoyalti from '@/Halaman/Kelola/Pelanggan/PengaturanLoyalti';
import HalamanTierPelanggan, { FormatPengali } from '@/Halaman/Kelola/Pelanggan/Tier';
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
    Tier: { Kode: 'GOLD', Nama: 'Gold' },
    TierTetap: false,
    SaldoPoin: 1250,
    LimitKredit: '5000000.00',
    TerminHari: 30,
    DibuatPada: '2026-09-20T02:00:00Z',
    JumlahTransaksi: 12,
    TotalBelanja: '12500000.00',
    TerakhirPada: '2026-09-24T05:30:00Z',
};

const OpsiTierUji = [
    { Nilai: 'SILVER', Label: 'Silver (SILVER)', Uuid: '01K5T1ER000000000000S1LVER' },
    { Nilai: 'GOLD', Label: 'Gold (GOLD)', Uuid: '01K5T1ER0000000000000G0LD1' },
];

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
                OpsiTier={OpsiTierUji}
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
                OpsiTier={[]}
                Izin={{ Kelola: false, LihatPenjualan: false }}
            />,
        );
        expect(screen.queryByRole('button', { name: 'Tambah pelanggan' })).toBeNull();
    });

    it('detail: profil, ringkasan, riwayat bertaut ke penjualan; arsipkan mengirim POST', () => {
        RenderUji(
            <HalamanDetailPelanggan
                Pelanggan={Ani}
                Kredit={{ LimitKredit: '5000000.00', SisaPiutang: '1250000.00', HariLewatJatuhTempo: 12 }}
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
                RiwayatPoin={[
                    {
                        Id: 1,
                        Jenis: 'Perolehan',
                        LabelJenis: 'Perolehan dari belanja',
                        Poin: 125,
                        Sisa: 125,
                        KedaluwarsaPada: '2027-09-24',
                        Keterangan: null,
                        DibuatPada: '2026-09-24T05:30:00Z',
                    },
                ]}
                OpsiTier={OpsiTierUji}
                LoyaltiBerlaku
                Izin={{ Kelola: true, LihatPenjualan: true }}
            />,
        );
        expect(screen.getByText('0812-3456-7890')).toBeTruthy();
        expect(screen.getAllByText('+125').length).toBeGreaterThan(0);
        expect(screen.getByText('1.250')).toBeTruthy();
        expect(screen.getByText('Setuju menerima')).toBeTruthy();
        expect(screen.getByText('Rp 5.000.000')).toBeTruthy();
        expect(screen.getByText('12 hari')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Lihat piutang' })).toBeTruthy();
        expect(screen.getAllByRole('link', { name: 'INV/SLB/260924/POS-001-0007' })[0]?.getAttribute('href')).toBe(
            '/kelola/penjualan/01K5JUAL000000000000000001',
        );
        fireEvent.click(screen.getByRole('button', { name: 'Arsipkan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(`/kelola/pelanggan/${Ani.Uuid}/arsipkan`, {}, expect.anything());

        // F-16b: penyesuaian poin manual.
        fireEvent.click(screen.getByRole('button', { name: 'Sesuaikan poin' }));
        fireEvent.change(screen.getByLabelText('Poin (+ tambah, − kurangi)'), { target: { value: '-50' } });
        fireEvent.change(screen.getByLabelText('Alasan'), { target: { value: 'Salah input kasir' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan penyesuaian' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            `/kelola/pelanggan/${Ani.Uuid}/poin`,
            { Poin: -50, Alasan: 'Salah input kasir' },
            expect.anything(),
        );
    });

    it('F-16b tier: daftar tier dengan pengali; tambah mengirim POST; tanpa fitur tampil ajakan paket', () => {
        RenderUji(
            <HalamanTierPelanggan
                Tier={[
                    {
                        Uuid: '01K5T1ER0000000000000G0LD1',
                        Kode: 'GOLD',
                        Nama: 'Gold',
                        MinimalBelanja: '5000000.00',
                        PengaliPoin: '1.50',
                        Urutan: 2,
                        Status: 'Aktif',
                        JumlahPelanggan: 12,
                    },
                ]}
                FiturAktif={false}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Loyalti tersedia di paket Pro ke atas')).toBeTruthy();
        expect(screen.getAllByText('×1,5').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Rp 5.000.000').length).toBeGreaterThan(0);
        fireEvent.click(screen.getAllByRole('button', { name: 'Tambah tier' })[0] as HTMLElement);
        fireEvent.change(screen.getByLabelText('Kode tier'), { target: { value: 'SILVER' } });
        fireEvent.change(screen.getByLabelText('Nama tier'), { target: { value: 'Silver' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan tier' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/pelanggan/tier',
            expect.objectContaining({
                Kode: 'SILVER',
                Nama: 'Silver',
                MinimalBelanja: '0',
                PengaliPoin: '1',
                Urutan: 0,
            }),
            expect.anything(),
        );
        expect(FormatPengali('2.00')).toBe('×2');
    });

    it('F-16b pengaturan loyalti: contoh perhitungan & simpan mengirim PUT', () => {
        RenderUji(
            <HalamanPengaturanLoyalti
                Pengaturan={{
                    Aktif: false,
                    BelanjaPerPoin: '10000.00',
                    MasaBerlakuBulan: 12,
                    BulanEvaluasiTier: 12,
                    NilaiTukarPoin: '100.00',
                    MinimalTukarPoin: 10,
                }}
                FiturAktif
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText(/mendapat 25 poin/)).toBeTruthy();
        expect(screen.getByText(/Menukar 100 poin memberi potongan Rp 10\.000/)).toBeTruthy();
        fireEvent.change(screen.getByLabelText('Minimal poin sekali tukar'), { target: { value: '50' } });
        fireEvent.click(screen.getByRole('checkbox'));
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pengaturan loyalti' }));
        expect(tiruanRouter.put).toHaveBeenCalledWith(
            '/kelola/pelanggan/loyalti',
            {
                Aktif: true,
                BelanjaPerPoin: '10000',
                MasaBerlakuBulan: 12,
                BulanEvaluasiTier: 12,
                NilaiTukarPoin: '100',
                MinimalTukarPoin: 50,
            },
            expect.anything(),
        );
    });
});

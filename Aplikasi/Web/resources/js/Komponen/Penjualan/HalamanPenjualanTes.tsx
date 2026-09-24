import { cleanup, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarPenjualan from '@/Halaman/Kelola/Penjualan/Daftar';
import HalamanDetailPenjualan from '@/Halaman/Kelola/Penjualan/Detail';
import { AturHalamanUji, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import type { BarisPenjualan, PropsDaftarPenjualan, PropsDetailPenjualan } from '@/Tipe/Penjualan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const barisPenjualan: BarisPenjualan = {
    Uuid: '01K5PENJUALAN0000000000001',
    Nomor: 'INV/UTAMA/260924/UTAMA-K01-0042',
    DibuatOfflinePada: '2026-09-24T03:10:00Z',
    TanggalBisnis: '2026-09-24',
    NamaOutlet: 'Toko Kelontong Berkah Solo',
    NamaKasir: 'Rina Wulandari',
    Kanal: 'BawaPulang',
    LabelKanal: 'Bawa pulang',
    TotalAkhir: '12596570.00',
    Metode: ['QRIS Toko Berkah', 'Tunai'],
    Status: 'Lunas',
    LabelStatus: 'Lunas',
    PerluTinjauan: true,
};

function PropsDaftar(data: BarisPenjualan[], outlet = 1): PropsDaftarPenjualan {
    return {
        Penjualan: { Data: data, Meta: { Halaman: 1, PerHalaman: 25, Total: data.length, JumlahHalaman: 1 } },
        OpsiOutlet: Array.from({ length: outlet }, (_, i) => ({
            Uuid: `01K5OUTLET000000000000000${String(i + 1)}`,
            Nama: `Outlet ${String(i + 1)}`,
        })),
        OpsiStatus: [
            { Nilai: 'Lunas', Label: 'Lunas' },
            { Nilai: 'Void', Label: 'Dibatalkan (void)' },
        ],
        OpsiKanal: [{ Nilai: 'BawaPulang', Label: 'Bawa pulang' }],
    };
}

const propsDetail: PropsDetailPenjualan = {
    Penjualan: {
        Uuid: barisPenjualan.Uuid,
        Nomor: barisPenjualan.Nomor,
        Status: 'Lunas',
        LabelStatus: 'Lunas',
        Kanal: 'BawaPulang',
        LabelKanal: 'Bawa pulang',
        NamaOutlet: 'Toko Kelontong Berkah Solo',
        Perangkat: 'UTAMA-K01 — Kasir Depan',
        NamaKasir: 'Rina Wulandari',
        NamaPenyetujuDiskon: 'Budi Santoso',
        DibuatOfflinePada: '2026-09-24T03:10:00Z',
        DiterimaPada: '2026-09-24T05:00:00Z',
        TanggalBisnis: '2026-09-24',
        HargaTermasukPajak: false,
        PersenBiayaLayanan: '0.00',
        Subtotal: '87000.00',
        DiskonBaris: '5000.00',
        DiskonPesanan: '0.00',
        TotalDiskon: '5000.00',
        BiayaLayanan: '0.00',
        TotalPajak: '9570.00',
        Pembulatan: '-70.00',
        TotalAkhir: '96500.00',
        TotalDibayar: '100000.00',
        Kembalian: '3500.00',
        TotalHpp: '60000.00',
        Catatan: 'Pelanggan minta struk digital',
        PerluTinjauan: true,
        AlasanTinjauan: 'StokTidakCukup: Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter (sisa −3)',
        UuidShift: '01K5SHIFT00000000000000001',
    },
    Baris: [
        {
            Uuid: '01K5BARIS0000000000000001',
            NamaProduk: 'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter',
            Jumlah: '2.0000',
            SimbolSatuan: 'pcs',
            HargaSatuan: '38500.00',
            HargaPilihan: '0.00',
            Pilihan: [],
            Bruto: '77000.00',
            JumlahDiskon: '5000.00',
            JumlahDiskonPesanan: '0.00',
            JumlahPajak: '8470.00',
            TotalBaris: '80470.00',
            HppSatuan: '30000.000000',
            TotalHpp: '60000.00',
            Catatan: null,
        },
    ],
    Pajak: [
        { KodeJenisPajak: 'Ppn', Tarif: '12.000000', DasarPengenaan: 'Subtotal', Dpp: '79750.00', Jumlah: '9570.00' },
    ],
    Pembayaran: [
        {
            Uuid: '01K5BAYAR0000000000000001',
            NamaMetode: 'QRIS Toko Berkah',
            LabelJenis: 'QRIS statis',
            Jumlah: '50000.00',
            Referensi: 'QR-88123',
        },
    ],
    MutasiStok: [
        {
            Kunci: '77',
            NamaProduk: 'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter',
            NamaGudang: 'Gudang Outlet Utama',
            Jumlah: '-2.0000',
            SimbolSatuan: 'pcs',
            TotalHpp: '-60000.00',
            TautanKartuStok:
                '/kelola/persediaan/kartu-stok?produk=01K5P&gudang=01K5G&dari=2026-09-24&sampai=2026-09-24',
        },
    ],
    Jurnal: [{ Uuid: '01K5JURNAL0000000000000009', Nomor: 'JU/2026/09/000009' }],
};

describe('F-07b halaman penjualan back-office', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/penjualan'));
    afterEach(() => cleanup());

    it('daftar (TabelData D-16): nomor bertautan detail, Rupiah jutaan terformat, metode, penanda perlu ditinjau, saring outlet hanya bila > 1; keadaan kosong', () => {
        window.history.replaceState({}, '', '/kelola/penjualan');
        RenderUji(<HalamanDaftarPenjualan {...PropsDaftar([barisPenjualan])} />);
        const tabel = screen.getByRole('table', { name: 'Daftar penjualan' });

        expect(within(tabel).getByRole('link', { name: barisPenjualan.Nomor }).getAttribute('href')).toBe(
            `/kelola/penjualan/${barisPenjualan.Uuid}`,
        );
        expect(within(tabel).getByText('Rp 12.596.570')).toBeTruthy();
        expect(within(tabel).getByText('QRIS Toko Berkah, Tunai')).toBeTruthy();
        expect(within(tabel).getByText('Perlu ditinjau')).toBeTruthy();
        expect(screen.getByRole('searchbox', { name: 'Cari di Daftar penjualan' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Hanya yang perlu ditinjau' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: /^Outlet/ })).toBeNull();

        cleanup();
        RenderUji(<HalamanDaftarPenjualan {...PropsDaftar([], 2)} />);
        expect(screen.getByText(/Belum ada penjualan/)).toBeTruthy();
        expect(screen.getByRole('button', { name: /^Outlet/ })).toBeTruthy();
    });

    it('detail: alasan tinjauan, ringkasan (pembulatan negatif), baris, pajak, pembayaran, mutasi stok bertautan kartu stok, jurnal & shift', () => {
        RenderUji(<HalamanDetailPenjualan {...propsDetail} />);

        expect(screen.getByText(/StokTidakCukup: Minyak Goreng/)).toBeTruthy();
        expect(screen.getByText('−Rp 70')).toBeTruthy();
        expect(screen.getByText('Rp 96.500')).toBeTruthy();
        expect(screen.getByText('Budi Santoso')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'JU/2026/09/000009' }).getAttribute('href')).toBe(
            '/kelola/akuntansi/jurnal/01K5JURNAL0000000000000009',
        );
        expect(screen.getByRole('link', { name: 'Lihat shift' }).getAttribute('href')).toBe(
            '/kelola/kasir/shift/01K5SHIFT00000000000000001',
        );
        expect(
            within(screen.getByRole('table', { name: 'Baris penjualan' })).getByText('2 pcs × Rp 38.500'),
        ).toBeTruthy();
        expect(
            within(screen.getByRole('table', { name: 'Rincian pajak penjualan' })).getByText('Ppn 12%'),
        ).toBeTruthy();
        expect(within(screen.getByRole('table', { name: 'Pembayaran penjualan' })).getByText('QR-88123')).toBeTruthy();
        const mutasi = within(screen.getByRole('table', { name: 'Mutasi stok penjualan' }));
        expect(mutasi.getByText('−2 pcs')).toBeTruthy();
        expect(mutasi.getByRole('link', { name: /Minyak Goreng/ }).getAttribute('href')).toContain(
            '/kelola/persediaan/kartu-stok?',
        );
    });

    it('detail tanpa jurnal, pajak, atau mutasi (jasa gratis): keadaan kosong jelas', () => {
        RenderUji(
            <HalamanDetailPenjualan
                {...propsDetail}
                Penjualan={{ ...propsDetail.Penjualan, PerluTinjauan: false, AlasanTinjauan: null, UuidShift: null }}
                Pajak={[]}
                MutasiStok={[]}
                Jurnal={[]}
            />,
        );

        expect(screen.queryByText(/perlu ditinjau/i)).toBeNull();
        expect(screen.getByText('Tanpa jurnal (nilai Rp 0)')).toBeTruthy();
        expect(screen.queryByRole('table', { name: 'Rincian pajak penjualan' })).toBeNull();
        expect(screen.getByText(/tidak mengurangi stok/)).toBeTruthy();
    });
});

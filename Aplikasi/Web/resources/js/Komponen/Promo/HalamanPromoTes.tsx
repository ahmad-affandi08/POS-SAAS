import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarPromo from '@/Halaman/Kelola/Promo/Daftar';
import HalamanFormulirPromo from '@/Halaman/Kelola/Promo/Formulir';
import HalamanVoucherPromo, { FormatBerlakuSampai } from '@/Halaman/Kelola/Promo/Voucher';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';
import type { BarisPromo, BarisVoucher } from '@/Tipe/Promo';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const HappyHour: BarisPromo = {
    Uuid: '01K5PROMO00000000000000001',
    Kode: 'HAPPY-HOUR',
    Nama: 'Happy hour 2 kopi Rp 30.000',
    JenisAksi: 'BundelHargaTetap',
    LabelAksi: 'Bundel harga tetap',
    Prioritas: 10,
    Eksklusif: false,
    MulaiPada: '2026-09-30T17:00:00Z',
    SelesaiPada: '2026-12-31T17:00:00Z',
    Kuota: 1000,
    KuotaTerpakai: 125,
    Status: 'Aktif',
    WajibVoucher: false,
    JumlahPakai: 125,
    TotalDiskon: '1250000.00',
    Definisi: null,
};

const opsi = {
    OpsiOutlet: [{ Nilai: '01K5OUTLET0000000000000001', Label: 'Solo' }],
    OpsiTier: [{ Nilai: 'GOLD', Label: 'Gold (GOLD)' }],
    OpsiKategori: [{ Nilai: '01K5KATEGORI00000000000001', Label: 'Minuman › Kopi' }],
    OpsiKanal: [
        { Nilai: 'MakanDiTempat', Label: 'Makan di tempat' },
        { Nilai: 'BawaPulang', Label: 'Bawa pulang' },
    ],
    OpsiMetodeBayar: [
        { Nilai: '01K5METODE0000000000000001', Label: 'Tunai' },
        { Nilai: '01K5METODE0000000000000002', Label: 'QRIS Toko' },
    ],
    OpsiUlangTahun: [
        { Nilai: 'Hari' as const, Label: 'Tepat di hari ulang tahun' },
        { Nilai: 'Rentang' as const, Label: 'Sekitar hari ulang tahun (± hari)' },
        { Nilai: 'Bulan' as const, Label: 'Sepanjang bulan ulang tahun' },
    ],
    OpsiPeriodeBatas: [
        { Nilai: 'Hari' as const, Label: 'Per hari' },
        { Nilai: 'Promo' as const, Label: 'Selama promo' },
    ],
};

describe('Halaman promo (F-16c)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/promo');
        window.history.replaceState({}, '', '/kelola/promo');
    });
    afterEach(() => cleanup());

    it('daftar: pemakaian/kuota & total potongan tampil; tanpa fitur tampil ajakan paket; tambah hanya untuk pengelola', () => {
        RenderUji(
            <HalamanDaftarPromo
                Promo={[HappyHour]}
                ModeResolusi="Terbaik"
                FiturAktif={false}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Mesin promo tersedia di paket Pro ke atas')).toBeTruthy();
        expect(screen.getAllByText('125 / 1.000').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Rp 1.250.000').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Tambah promo' })[0]?.getAttribute('href')).toBe(
            '/kelola/promo/buat',
        );

        cleanup();
        RenderUji(<HalamanDaftarPromo Promo={[]} ModeResolusi="PrioritasKetat" FiturAktif Izin={{ Kelola: false }} />);
        expect(screen.queryByRole('link', { name: 'Tambah promo' })).toBeNull();
        expect(screen.getByText(/Promo dipakai urut prioritas/)).toBeTruthy();
    });

    it('formulir: diskon persen kategori tertentu khusus tier GOLD dikirim sebagai POST', () => {
        RenderUji(<HalamanFormulirPromo Promo={null} FiturAktif {...opsi} />);
        fireEvent.change(screen.getByLabelText('Kode promo'), { target: { value: 'gold10' } });
        fireEvent.change(screen.getByLabelText('Nama promo'), { target: { value: 'Member Gold 10% kopi' } });
        fireEvent.change(screen.getByLabelText('Persen diskon'), { target: { value: '10' } });
        fireEvent.click(screen.getByLabelText('Gold (GOLD)'));
        fireEvent.click(screen.getByRole('button', { name: 'Simpan promo' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/promo',
            expect.objectContaining({
                Kode: 'GOLD10',
                Nama: 'Member Gold 10% kopi',
                JenisAksi: 'DiskonPersenItem',
                Persen: '10',
                Tier: ['GOLD'],
                JenisKondisi: 'Semua',
                UuidKondisi: [],
                Jumlah: null,
                BatasPerTransaksi: null,
                WajibVoucher: false,
            }),
            expect.anything(),
        );
    });

    it('formulir (F-16c bagian 4): promo poin berlipat menampilkan pengali dan mengirimnya tanpa nilai potongan', () => {
        const poin = {
            ...HappyHour,
            Kode: 'POIN2X',
            Nama: 'Poin 2 kali akhir pekan',
            JenisAksi: 'PoinBerlipat' as const,
            LabelAksi: 'Poin berlipat',
            Definisi: {
                Hari: [6, 7],
                JamMulai: null,
                JamSelesai: null,
                Outlet: [],
                Kanal: [],
                Tier: [],
                MinimalSubtotal: '0.00',
                Kondisi: { Jenis: 'Semua' as const, Uuid: [], JumlahMinimal: '0.0000' },
                Aksi: { Jenis: 'PoinBerlipat' as const, Pengali: '2' },
                BatasPerTransaksi: null,
            },
            TanggalMulai: null,
            TanggalSelesai: null,
            NamaProduk: {},
        };
        RenderUji(<HalamanFormulirPromo Promo={poin} FiturAktif {...opsi} />);
        fireEvent.change(screen.getByLabelText('Pengali poin'), { target: { value: '1,5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan promo' }));
        expect(tiruanRouter.put).toHaveBeenCalledWith(
            `/kelola/promo/${HappyHour.Uuid}`,
            expect.objectContaining({
                JenisAksi: 'PoinBerlipat',
                Pengali: '1.5',
                Persen: null,
                Jumlah: null,
                Harga: null,
            }),
            expect.anything(),
        );
    });

    it('formulir (F-16c bagian 3): metode bayar QRIS, transaksi pertama, dan batas 1x per hari dikirim', () => {
        RenderUji(<HalamanFormulirPromo Promo={null} FiturAktif {...opsi} />);
        fireEvent.change(screen.getByLabelText('Kode promo'), { target: { value: 'qris-baru' } });
        fireEvent.change(screen.getByLabelText('Nama promo'), { target: { value: 'Pelanggan baru bayar QRIS 10%' } });
        fireEvent.change(screen.getByLabelText('Persen diskon'), { target: { value: '10' } });
        fireEvent.click(screen.getByLabelText('QRIS Toko'));
        fireEvent.click(screen.getByLabelText('Hanya transaksi pertama pelanggan'));
        fireEvent.change(screen.getByLabelText('Batas pakai per pelanggan'), { target: { value: '1' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan promo' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/promo',
            expect.objectContaining({
                Kode: 'QRIS-BARU',
                MetodeBayar: ['01K5METODE0000000000000002'],
                UlangTahun: null,
                HariUlangTahun: null,
                TransaksiPertama: true,
                BatasPerPelanggan: 1,
                PeriodeBatasPelanggan: 'Hari',
            }),
            expect.anything(),
        );
    });

    it('voucher (F-16c bagian 2): pemakaian & pesanan kasir tampil; buat kode massal berawalan dikirim sebagai POST', () => {
        expect(FormatBerlakuSampai(null)).toBe('Ikut periode promo');
        expect(FormatBerlakuSampai('2026-10-31T17:00:00Z')).toBe('31 Okt 2026');
        const promo: BarisPromo & { Definisi: null } = {
            ...HappyHour,
            Kode: 'VCR-HUT',
            Nama: 'Voucher HUT Rp 10.000',
            WajibVoucher: true,
            Definisi: null,
        };
        const voucher: BarisVoucher = {
            Uuid: '01K5VOUCHER000000000000001',
            Kode: 'HUT7K2M9QXA',
            MaksimalPakai: 1,
            JumlahDipakai: 0,
            Dipesan: 1,
            KedaluwarsaPada: null,
            Status: 'Aktif',
            DibuatPada: '2026-09-25T03:00:00Z',
        };
        window.history.replaceState({}, '', `/kelola/promo/${promo.Uuid}/voucher`);
        RenderUji(
            <HalamanVoucherPromo
                Promo={promo}
                Voucher={BuatHasilTabel([voucher])}
                Ringkasan={{ Total: 1, Aktif: 1, Dipakai: 0 }}
                JumlahMaksimal={5000}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getAllByText('HUT7K2M9QXA').length).toBeGreaterThan(0);
        expect(screen.getAllByText('0 / 1 · 1 sedang di kasir').length).toBeGreaterThan(0);

        fireEvent.click(screen.getAllByRole('button', { name: 'Tambah voucher' })[0] as HTMLElement);
        fireEvent.change(screen.getByLabelText('Awalan kode (opsional)'), { target: { value: 'hut' } });
        fireEvent.click(screen.getByRole('button', { name: 'Buat voucher' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            `/kelola/promo/${promo.Uuid}/voucher`,
            {
                Cara: 'Massal',
                Kode: null,
                Jumlah: 100,
                Awalan: 'HUT',
                MaksimalPakai: 1,
                TanggalKedaluwarsa: null,
            },
            expect.anything(),
        );

        cleanup();
        RenderUji(
            <HalamanVoucherPromo
                Promo={{ ...promo, WajibVoucher: false }}
                Voucher={BuatHasilTabel<BarisVoucher>([])}
                Ringkasan={{ Total: 0, Aktif: 0, Dipakai: 0 }}
                JumlahMaksimal={5000}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Promo ini diterapkan otomatis tanpa kode')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Tambah voucher' })).toBeNull();
    });
});

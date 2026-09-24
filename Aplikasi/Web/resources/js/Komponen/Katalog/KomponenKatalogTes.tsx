import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { BarisHarga } from '@/Tipe/Katalog';

import { AmbilGalatBerawalan, HitungKombinasiVarian, PindahkanItem } from './BantuanKatalog';
import BidangBarcode from './BidangBarcode';
import BidangJumlah from './BidangJumlah';
import PemilihProduk, { BuatUrlCariProduk } from './PemilihProduk';
import TabelHargaBertingkat, { PeriksaBarisHarga, RingkasBarisHarga, UrutkanBarisHarga } from './TabelHargaBertingkat';
import { RenderUji } from './TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('./TiruanInertia')).TiruanInertia);

function JumlahUji({
    awal = '',
    desimal,
    saatBerubah,
}: {
    awal?: string;
    desimal?: number;
    saatBerubah?: (n: string) => void;
}) {
    const [nilai, AturNilai] = useState(awal);

    return (
        <BidangJumlah
            label="Jumlah"
            nilai={nilai}
            {...(desimal === undefined ? {} : { desimal })}
            akhiran="kg"
            saatBerubah={(baru) => {
                AturNilai(baru);
                saatBerubah?.(baru);
            }}
        />
    );
}

describe('BidangJumlah (kuantitas string, maks 4 desimal)', () => {
    afterEach(() => cleanup());

    it('menampilkan format Indonesia rata kanan dan mengirim string desimal polos', () => {
        const SaatBerubah = vi.fn();
        render(<JumlahUji awal="1250.5000" saatBerubah={SaatBerubah} />);
        const input = screen.getByLabelText<HTMLInputElement>('Jumlah');

        expect(input.value).toBe('1.250,5');
        expect(input.className).toContain('text-right');
        expect(input.className).toContain('tabular-nums');

        fireEvent.change(input, { target: { value: '0,' } });
        expect(input.value).toBe('0,');
        expect(SaatBerubah).toHaveBeenLastCalledWith('0');

        fireEvent.change(input, { target: { value: '0,25' } });
        expect(SaatBerubah).toHaveBeenLastCalledWith('0.25');

        fireEvent.change(input, { target: { value: '0,12345' } });
        expect(SaatBerubah).toHaveBeenLastCalledWith('0.25');

        fireEvent.blur(input);
        expect(input.value).toBe('0,25');
    });

    it('satuan tanpa desimal menolak koma', () => {
        const SaatBerubah = vi.fn();
        render(<JumlahUji desimal={0} saatBerubah={SaatBerubah} />);
        const input = screen.getByLabelText<HTMLInputElement>('Jumlah');

        fireEvent.change(input, { target: { value: '12,5' } });
        expect(SaatBerubah).not.toHaveBeenCalled();
        expect(input.getAttribute('inputmode')).toBe('numeric');
    });
});

function BarcodeUji({ awal = [] as string[] }) {
    const [nilai, AturNilai] = useState(awal);

    return <BidangBarcode label="Barcode pcs" nilai={nilai} saatBerubah={AturNilai} barcodeLain={['8990001']} />;
}

describe('BidangBarcode (BR-03.1)', () => {
    afterEach(() => cleanup());

    it('Enter dari pemindai menambah barcode tanpa mengirim formulir; duplikat ditolak', () => {
        render(<BarcodeUji awal={['8991234567890']} />);
        const input = screen.getByLabelText<HTMLInputElement>('Barcode pcs');

        fireEvent.change(input, { target: { value: '8997000111222' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(screen.getByText('8997000111222').className).toContain('font-mono');
        expect(input.value).toBe('');

        fireEvent.change(input, { target: { value: '8990001' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(screen.getByText('Barcode 8990001 sudah dipakai di produk ini.')).toBeTruthy();

        fireEvent.change(input, { target: { value: 'ab' } });
        fireEvent.click(screen.getByRole('button', { name: 'Tambah barcode' }));
        expect(screen.getByText('Barcode 3–64 karakter: huruf, angka, titik, atau strip.')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Hapus barcode 8991234567890' }));
        expect(screen.queryByText('8991234567890')).toBeNull();
    });
});

describe('TabelHargaBertingkat (harga dasar + bertingkat, string saja)', () => {
    afterEach(() => cleanup());

    it('memvalidasi baris tanpa number: dasar wajib, duplikat, pecahan pada satuan bulat', () => {
        const baris: BarisHarga[] = [
            { JumlahMinimum: '12', Harga: '4500' },
            { JumlahMinimum: '12.0000', Harga: '' },
            { JumlahMinimum: '2.5', Harga: '4000' },
            { JumlahMinimum: '0', Harga: '1' },
        ];
        const { perBaris, umum } = PeriksaBarisHarga(baris, { wajibDasar: true, bolehDesimal: false });

        expect(umum).toBe('Harga dasar untuk jumlah minimum 1 wajib ada.');
        expect(perBaris[0]).toBeUndefined();
        expect(perBaris[1]).toEqual({
            JumlahMinimum: 'Jumlah minimum ini sudah ada di baris lain.',
            Harga: 'Isi harga. Tulis 0 bila gratis.',
        });
        expect(perBaris[2]?.JumlahMinimum).toBe('Satuan ini tidak boleh pecahan. Isi bilangan bulat.');
        expect(perBaris[3]?.JumlahMinimum).toBe('Isi jumlah minimum lebih dari 0.');
        expect(
            PeriksaBarisHarga([{ JumlahMinimum: '12', Harga: '0' }], { wajibDasar: false, bolehDesimal: false }),
        ).toEqual({ perBaris: {}, umum: null });
    });

    it('mengurutkan dengan perbandingan desimal string (9 < 12 < 100)', () => {
        const hasil = UrutkanBarisHarga([
            { JumlahMinimum: '100', Harga: '1' },
            { JumlahMinimum: '9', Harga: '1' },
            { JumlahMinimum: '', Harga: '1' },
            { JumlahMinimum: '12', Harga: '1' },
        ]);

        expect(hasil.map((item) => item.JumlahMinimum)).toEqual(['9', '12', '100', '']);
        expect(RingkasBarisHarga({ JumlahMinimum: '12.0000', Harga: '4500.00' }, 'pcs')).toBe('12+ pcs: Rp 4.500');
    });

    it('baris dasar terkunci, tingkat baru bisa ditambah dan dihapus', () => {
        function Uji() {
            const [baris, AturBaris] = useState<BarisHarga[]>([{ JumlahMinimum: '1.0000', Harga: '5000.00' }]);

            return (
                <TabelHargaBertingkat
                    judul="Harga per pcs"
                    baris={baris}
                    saatBerubah={AturBaris}
                    simbolSatuan="pcs"
                    bolehDesimal={false}
                    wajibDasar
                />
            );
        }

        render(<Uji />);

        expect(screen.getByLabelText<HTMLInputElement>('Mulai jumlah baris 1').disabled).toBe(true);
        expect(screen.getByLabelText<HTMLInputElement>('Harga baris 1').value).toBe('5.000');

        fireEvent.click(screen.getByRole('button', { name: 'Tambah harga bertingkat' }));
        fireEvent.change(screen.getByLabelText('Mulai jumlah baris 2'), { target: { value: '12' } });
        fireEvent.change(screen.getByLabelText('Harga baris 2'), { target: { value: '4.500' } });
        expect(screen.getByLabelText<HTMLInputElement>('Harga baris 2').value).toBe('4.500');

        fireEvent.click(screen.getByRole('button', { name: 'Hapus tingkat harga baris 2' }));
        expect(screen.queryByLabelText('Harga baris 2')).toBeNull();
    });

    it('tanpa baris: menjelaskan satuan hanya untuk pembelian', () => {
        render(
            <TabelHargaBertingkat
                judul="Harga per dus"
                baris={[]}
                saatBerubah={vi.fn()}
                simbolSatuan="dus"
                bolehDesimal={false}
                wajibDasar
            />,
        );

        expect(screen.getByText(/satuan ini hanya untuk pembelian/)).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Isi harga dasar' })).toBeTruthy();
    });
});

describe('PemilihProduk (TanStack Query, KunciKueri.Produk.Cari)', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('membangun URL pencarian dengan jenis[]', () => {
        expect(BuatUrlCariProduk('susu uht', ['BahanBaku', 'Stok'])).toBe(
            '/kelola/produk/cari?kata=susu+uht&jenis%5B%5D=BahanBaku&jenis%5B%5D=Stok&batas=20',
        );
    });

    it('mencari, menampilkan hasil, dan memilih dengan keyboard; produk yang dikecualikan disembunyikan', async () => {
        const Ambil = vi.fn().mockResolvedValue({
            ok: true,
            json: () =>
                Promise.resolve({
                    Data: [
                        {
                            Uuid: 'P-SUSU',
                            Nama: 'Susu UHT 1 L',
                            Sku: 'PRD-000012',
                            Jenis: 'BahanBaku',
                            UuidSatuanDasar: 'SAT-ML',
                            Satuan: [
                                {
                                    Uuid: 'SAT-ML',
                                    Nama: 'Mililiter',
                                    Simbol: 'ml',
                                    BolehDesimal: true,
                                    KonversiKeDasar: '1.0000',
                                },
                            ],
                        },
                        {
                            Uuid: 'P-DIRI',
                            Nama: 'Susu diri sendiri',
                            Sku: null,
                            Jenis: 'Stok',
                            UuidSatuanDasar: 'SAT-PCS',
                            Satuan: [],
                        },
                    ],
                }),
        });
        vi.stubGlobal('fetch', Ambil);
        const SaatPilih = vi.fn();

        RenderUji(
            <PemilihProduk
                label="Cari bahan"
                jenis={['BahanBaku', 'Stok']}
                kecuali={['P-DIRI']}
                saatPilih={SaatPilih}
            />,
        );
        const input = screen.getByRole('combobox', { name: 'Cari bahan' });

        fireEvent.focus(input);
        fireEvent.change(input, { target: { value: 'su' } });

        await waitFor(() => expect(screen.getByRole('option', { name: /Susu UHT 1 L/ })).toBeTruthy());
        expect(screen.queryByRole('option', { name: /diri sendiri/ })).toBeNull();
        expect(Ambil.mock.calls[0]?.[0]).toBe(
            '/kelola/produk/cari?kata=su&jenis%5B%5D=BahanBaku&jenis%5B%5D=Stok&batas=20',
        );

        fireEvent.keyDown(input, { key: 'Enter' });
        expect(SaatPilih).toHaveBeenCalledWith(expect.objectContaining({ Uuid: 'P-SUSU' }));
    });

    it('galat jaringan dan hasil kosong ditulis di area aria-live', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500, json: () => Promise.resolve({}) }));
        RenderUji(<PemilihProduk label="Cari komponen" jenis={['Stok']} saatPilih={vi.fn()} />);
        const input = screen.getByRole('combobox', { name: 'Cari komponen' });

        fireEvent.focus(input);
        fireEvent.change(input, { target: { value: 'x' } });
        expect(screen.getByText('Ketik minimal 2 huruf.')).toBeTruthy();

        await act(async () => {
            fireEvent.change(input, { target: { value: 'xyz' } });
            await Promise.resolve();
        });
        await waitFor(() =>
            expect(screen.getByText('Pencarian gagal. Periksa koneksi lalu ketik ulang.')).toBeTruthy(),
        );
    });
});

describe('BantuanKatalog', () => {
    it('memetakan galat server bersarang ke kunci relatif', () => {
        expect(
            AmbilGalatBerawalan(
                { 'Satuan.0.Barcode.1': 'Barcode sudah dipakai', 'Satuan.1.Harga': 'x', Sku: 'SKU sudah dipakai' },
                'Satuan.0',
            ),
        ).toEqual({ 'Barcode.1': 'Barcode sudah dipakai' });
    });

    it('menghitung kombinasi varian kartesius dan memindahkan urutan', () => {
        expect(
            HitungKombinasiVarian([
                { Nama: 'Ukuran', Nilai: ['M', 'L'] },
                { Nama: 'Warna', Nilai: ['Merah', 'Biru'] },
                { Nama: 'Kosong', Nilai: [] },
            ]),
        ).toEqual([
            ['M', 'Merah'],
            ['M', 'Biru'],
            ['L', 'Merah'],
            ['L', 'Biru'],
        ]);
        expect(HitungKombinasiVarian([])).toEqual([]);
        expect(PindahkanItem(['a', 'b', 'c'], 2, 0)).toEqual(['c', 'a', 'b']);
        expect(PindahkanItem(['a', 'b'], 0, 5)).toEqual(['a', 'b']);
    });
});

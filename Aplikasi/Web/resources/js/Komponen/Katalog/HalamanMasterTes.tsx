import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarDaftarHarga, { RingkasPeriode } from '@/Halaman/Kelola/DaftarHarga/Daftar';
import HalamanDetailDaftarHarga, { AmbilBarisBerubah } from '@/Halaman/Kelola/DaftarHarga/Detail';
import HalamanDaftarKategori, { AmbilTurunanKategori } from '@/Halaman/Kelola/Kategori/Daftar';
import HalamanDaftarKelompokPajak, { PeriksaKonsistensiPajak } from '@/Halaman/Kelola/KelompokPajak/Daftar';
import HalamanDaftarKelompokPilihan, {
    PeriksaKelompokPilihan,
    RingkasAturanPilih,
} from '@/Halaman/Kelola/KelompokPilihan/Daftar';
import HalamanDaftarSatuan from '@/Halaman/Kelola/Satuan/Daftar';
import type {
    PropsDaftarKategori,
    PropsDaftarKelompokPajak,
    PropsDaftarKelompokPilihan,
    PropsDetailDaftarHarga,
} from '@/Tipe/Katalog';

import { PeriksaRentangWaktu } from './FormDaftarHarga';
import { BuatHasilTabel, IzinLihat, IzinPenuh } from './DataUjiKatalog';
import { AturHalamanUji, kirimanForm, RenderUji, tiruanRouter } from './TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('./TiruanInertia')).TiruanInertia);

const kategori: PropsDaftarKategori['Kategori'] = [
    { Uuid: 'K1', Nama: 'Minuman', Jalur: 'Minuman', Kedalaman: 1, UuidInduk: null, JumlahProduk: 0, Urutan: 0 },
    { Uuid: 'K2', Nama: 'Kopi', Jalur: 'Minuman › Kopi', Kedalaman: 2, UuidInduk: 'K1', JumlahProduk: 4, Urutan: 1 },
    {
        Uuid: 'K3',
        Nama: 'Kopi susu',
        Jalur: 'Minuman › Kopi › Kopi susu',
        Kedalaman: 3,
        UuidInduk: 'K2',
        JumlahProduk: 0,
        Urutan: 0,
    },
    { Uuid: 'K4', Nama: 'Makanan', Jalur: 'Makanan', Kedalaman: 1, UuidInduk: null, JumlahProduk: 0, Urutan: 2 },
];

describe('Kelola/Kategori & Satuan (E.5)', () => {
    beforeEach(() => AturHalamanUji());
    afterEach(() => cleanup());

    it('induk tidak boleh diri sendiri/turunan atau tingkat 3; hapus hanya kategori kosong tanpa anak', () => {
        expect([...AmbilTurunanKategori(kategori, 'K1')].sort()).toEqual(['K1', 'K2', 'K3']);
        render(<HalamanDaftarKategori Kategori={kategori} Izin={IzinPenuh} />);

        expect(screen.queryByRole('button', { name: 'Hapus kategori Minuman' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Hapus kategori Kopi' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Hapus kategori Kopi susu' })).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Ubah kategori Minuman' }));
        const opsiInduk = Array.from(screen.getByLabelText<HTMLSelectElement>('Induk kategori').options).map(
            (o) => o.value,
        );
        expect(opsiInduk).toEqual(['', 'K4']);

        fireEvent.change(screen.getByLabelText('Nama kategori'), { target: { value: 'Minuman dingin' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan kategori' }));
        expect(kirimanForm[0]).toEqual({
            metode: 'put',
            url: '/kelola/kategori/K1',
            data: { Nama: 'Minuman dingin', UuidInduk: null, Urutan: '0' },
        });
    });

    it('kosong & tanpa izin', () => {
        render(<HalamanDaftarKategori Kategori={[]} Izin={IzinLihat} />);

        expect(screen.getByText('Belum ada kategori. Tambah kategori agar produk mudah dicari di kasir.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Tambah kategori' })).toBeNull();
    });

    it('satuan: kode standar Mono, tambah satuan POST', () => {
        render(
            <HalamanDaftarSatuan
                Satuan={[
                    {
                        Uuid: 'S1',
                        Nama: 'Kilogram',
                        Simbol: 'kg',
                        BolehDesimal: true,
                        KodeStandar: 'KGM',
                        JumlahProduk: 3,
                    },
                    {
                        Uuid: 'S2',
                        Nama: 'Porsi',
                        Simbol: 'porsi',
                        BolehDesimal: false,
                        KodeStandar: null,
                        JumlahProduk: 0,
                    },
                ]}
                Izin={IzinPenuh}
            />,
        );

        expect(screen.getByText('KGM').className).toContain('font-mono');
        expect(screen.queryByRole('button', { name: 'Hapus satuan Kilogram' })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Tambah satuan' }));
        fireEvent.change(screen.getByLabelText('Nama satuan'), { target: { value: 'Dus' } });
        fireEvent.change(screen.getByLabelText('Simbol'), { target: { value: 'dus' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan satuan' }));
        expect(kirimanForm[0]).toEqual({
            metode: 'post',
            url: '/kelola/satuan',
            data: { Nama: 'Dus', Simbol: 'dus', BolehDesimal: false },
        });
    });
});

describe('Kelola/DaftarHarga (E.7)', () => {
    beforeEach(() => AturHalamanUji());
    afterEach(() => cleanup());

    it('periode & rentang waktu dibandingkan sebagai teks lokal', () => {
        expect(PeriksaRentangWaktu('2026-10-01T00:00', '2026-10-01T00:00')).toBe(
            'Waktu selesai harus setelah waktu mulai.',
        );
        expect(PeriksaRentangWaktu('2026-10-01T00:00', '2026-11-01T00:00')).toBeNull();
        expect(PeriksaRentangWaktu('', '2026-11-01T00:00')).toBeNull();
        expect(RingkasPeriode({ MulaiPada: null, SelesaiPada: null })).toBe('Selalu');
    });

    it('daftar: kosong, lalu buat daftar harga mengirim FormDaftarHarga', () => {
        window.history.replaceState({}, '', '/kelola/daftar-harga');
        RenderUji(
            <HalamanDaftarDaftarHarga
                DaftarHarga={BuatHasilTabel([])}
                Outlet={[{ Nilai: 'O-SLO', Label: 'Solo' }]}
                Kanal={[{ Nilai: 'Online', Label: 'Online' }]}
                ZonaWaktu="WIB"
                Izin={IzinPenuh}
            />,
        );

        expect(screen.getByText('Belum ada daftar harga. Semua produk memakai harga dasar.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Buat daftar harga' }));
        fireEvent.change(screen.getByLabelText('Nama daftar harga'), { target: { value: 'Harga GoFood' } });
        fireEvent.change(screen.getByLabelText('Kanal penjualan'), { target: { value: 'Online' } });
        fireEvent.change(screen.getByLabelText('Mulai berlaku (opsional)'), { target: { value: '2026-11-01T00:00' } });
        fireEvent.change(screen.getByLabelText('Selesai berlaku (opsional)'), {
            target: { value: '2026-10-01T00:00' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Buat daftar harga' }));
        expect(kirimanForm).toHaveLength(0);
        expect(screen.getAllByText('Waktu selesai harus setelah waktu mulai.').length).toBeGreaterThan(0);

        fireEvent.change(screen.getByLabelText('Selesai berlaku (opsional)'), { target: { value: '' } });
        fireEvent.click(screen.getByLabelText('Solo'));
        fireEvent.click(screen.getByRole('button', { name: 'Buat daftar harga' }));
        expect(kirimanForm[0]).toEqual({
            metode: 'post',
            url: '/kelola/daftar-harga',
            data: {
                Nama: 'Harga GoFood',
                UuidOutlet: ['O-SLO'],
                Kanal: 'Online',
                TierPelanggan: '',
                MulaiPada: '2026-11-01T00:00',
                SelesaiPada: '',
                Prioritas: '0',
            },
        });
    });

    it('detail: hanya baris yang berubah dikirim sebagai { Baris }', () => {
        const props: PropsDetailDaftarHarga = {
            DaftarHarga: {
                Uuid: 'DH-1',
                Aktif: true,
                Nama: 'Grosir',
                UuidOutlet: [],
                Kanal: '',
                TierPelanggan: 'GROSIR',
                MulaiPada: '',
                SelesaiPada: '',
                Prioritas: '10',
            },
            Baris: BuatHasilTabel([
                {
                    UuidProduk: 'P1',
                    NamaProduk: 'Sabun',
                    Sku: 'PRD-000001',
                    UuidProdukSatuan: 'PS-1',
                    NamaSatuan: 'pcs',
                    HargaDasar: '5000.00',
                    Harga: [],
                },
                {
                    UuidProduk: 'P2',
                    NamaProduk: 'Sampo',
                    Sku: null,
                    UuidProdukSatuan: 'PS-2',
                    NamaSatuan: 'pcs',
                    HargaDasar: null,
                    Harga: [{ JumlahMinimum: '12.0000', Harga: '4000.00' }],
                },
            ]),
            Outlet: [],
            Kanal: [],
            ZonaWaktu: 'WIB',
            Izin: IzinPenuh,
        };

        expect(AmbilBarisBerubah(props.Baris.Data, { 'PS-1': [], 'PS-2': props.Baris.Data[1]?.Harga ?? [] })).toEqual(
            [],
        );
        window.history.replaceState({}, '', '/kelola/daftar-harga/DH-1');
        RenderUji(<HalamanDetailDaftarHarga {...props} />);
        expect(screen.getByText('Semua outlet · Semua kanal · Pelanggan GROSIR · Prioritas 10')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Isi harga' }));
        const [hargaSabun] = screen.getAllByLabelText('Harga baris 1');
        fireEvent.change(hargaSabun ?? document.body, { target: { value: '4.800' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan harga' }));
        expect(tiruanRouter.put).toHaveBeenCalledWith(
            '/kelola/daftar-harga/DH-1/harga',
            { Baris: [{ UuidProdukSatuan: 'PS-1', Harga: [{ JumlahMinimum: '1', Harga: '4800' }] }] },
            expect.anything(),
        );
    });
});

describe('Kelola/KelompokPajak (E.8)', () => {
    beforeEach(() => AturHalamanUji());
    afterEach(() => cleanup());

    const props: PropsDaftarKelompokPajak = {
        KelompokPajak: [
            {
                Uuid: 'KP-1',
                Nama: 'Makan & minum',
                Kategori: 'KenaPbjt',
                LabelKategori: 'Kena PBJT',
                Pajak: [
                    {
                        KodeJenisPajak: 'PbjtMakananMinuman',
                        NamaJenisPajak: 'PBJT makanan & minuman',
                        DasarPengenaan: 'SubtotalPlusLayanan',
                        LabelDasarPengenaan: 'Subtotal + biaya layanan',
                    },
                ],
                JumlahProduk: 12,
            },
        ],
        JenisPajak: [
            { Kode: 'Ppn', Nama: 'PPN', Cakupan: 'Nasional' },
            { Kode: 'PbjtMakananMinuman', Nama: 'PBJT makanan & minuman', Cakupan: 'Daerah' },
        ],
        Kategori: [
            { Nilai: 'KenaPpn', Label: 'Kena PPN' },
            { Nilai: 'KenaPbjt', Label: 'Kena PBJT' },
            { Nilai: 'NonPajak', Label: 'Bukan objek pajak' },
        ],
        DasarPengenaan: [{ Nilai: 'SubtotalPlusLayanan', Label: 'Subtotal + biaya layanan' }],
        Izin: IzinPenuh,
    };

    it('aturan konsistensi PPN/PBJT (§12.1) diperiksa sebelum dikirim', () => {
        expect(PeriksaKonsistensiPajak('KenaPbjt', ['PbjtMakananMinuman'])).toBeNull();
        expect(PeriksaKonsistensiPajak('KenaPbjt', ['PbjtMakananMinuman', 'Ppn'])).not.toBeNull();
        expect(PeriksaKonsistensiPajak('KenaPpn', ['Ppn'])).toBeNull();
        expect(PeriksaKonsistensiPajak('NonPajak', [])).toBeNull();
        expect(PeriksaKonsistensiPajak('Lainnya', ['Ppn'])).not.toBeNull();
        expect(PeriksaKonsistensiPajak('KenaPpn', ['Ppn', 'Ppn'])).toBe(
            'Jenis pajak yang sama tidak boleh dipilih dua kali.',
        );
    });

    it('memakai istilah "biaya layanan"; ubah hanya dengan izin akuntansi.kelola', () => {
        render(<HalamanDaftarKelompokPajak {...props} />);
        expect(screen.getByText('PBJT makanan & minuman · Subtotal + biaya layanan')).toBeTruthy();
        expect(document.body.textContent).not.toMatch(/service charge/i);

        fireEvent.click(screen.getByRole('button', { name: 'Ubah kelompok pajak Makan & minum' }));
        fireEvent.change(screen.getByLabelText('Kategori pajak'), { target: { value: 'NonPajak' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan kelompok pajak' }));
        expect(kirimanForm[0]).toEqual({
            metode: 'put',
            url: '/kelola/kelompok-pajak/KP-1',
            data: { Nama: 'Makan & minum', Kategori: 'NonPajak', Pajak: [] },
        });
        cleanup();

        render(<HalamanDaftarKelompokPajak {...props} Izin={{ ...IzinPenuh, KelolaPajak: false }} />);
        expect(screen.queryByRole('button', { name: /Ubah kelompok pajak/ })).toBeNull();
        expect(screen.getByText('akuntansi.kelola')).toBeTruthy();
    });
});

describe('Kelola/KelompokPilihan (E.9)', () => {
    beforeEach(() => AturHalamanUji());
    afterEach(() => cleanup());

    it('aturan minimal/maksimal pilih dan nama unik', () => {
        const BuatPilihan = (nama: string, aktif = true) => ({
            Uuid: null,
            Nama: nama,
            Harga: '0',
            Aktif: aktif,
            UuidProdukBahan: null,
            Jumlah: '',
        });

        expect(
            PeriksaKelompokPilihan({ MinimalPilih: '1', MaksimalPilih: '1', Pilihan: [BuatPilihan('Normal')] }),
        ).toBeNull();
        expect(
            PeriksaKelompokPilihan({
                MinimalPilih: '2',
                MaksimalPilih: '1',
                Pilihan: [BuatPilihan('A'), BuatPilihan('B')],
            }),
        ).toBe('Minimal pilih tidak boleh lebih besar dari maksimal pilih.');
        expect(
            PeriksaKelompokPilihan({ MinimalPilih: '1', MaksimalPilih: '2', Pilihan: [BuatPilihan('A', false)] }),
        ).toBe('Minimal pilih 1, tetapi hanya 0 pilihan aktif.');
        expect(
            PeriksaKelompokPilihan({
                MinimalPilih: '0',
                MaksimalPilih: '2',
                Pilihan: [BuatPilihan('A'), BuatPilihan('a')],
            }),
        ).toBe('Setiap pilihan perlu nama, dan nama tidak boleh sama dalam satu kelompok.');
        expect(RingkasAturanPilih('1', '1')).toBe('Wajib pilih 1');
        expect(RingkasAturanPilih('0', '3')).toBe('Opsional, maks 3');
    });

    it('daftar menampilkan harga tambahan & bahan; simpan tanpa kolom tampilan', () => {
        const props: PropsDaftarKelompokPilihan = {
            KelompokPilihan: [
                {
                    Uuid: 'KP-TOP',
                    Nama: 'Topping',
                    MinimalPilih: '0',
                    MaksimalPilih: '3',
                    Urutan: '0',
                    Wajib: false,
                    JumlahProduk: 4,
                    Pilihan: [
                        {
                            Uuid: 'PL-1',
                            Nama: 'Keju',
                            Harga: '5000.00',
                            Aktif: true,
                            UuidProdukBahan: 'P-KEJU',
                            Jumlah: '20.0000',
                            NamaProdukBahan: 'Keju parut',
                            SimbolSatuanBahan: 'g',
                        },
                        {
                            Uuid: 'PL-2',
                            Nama: 'Tanpa topping',
                            Harga: '0.00',
                            Aktif: true,
                            UuidProdukBahan: null,
                            Jumlah: '',
                            NamaProdukBahan: null,
                            SimbolSatuanBahan: null,
                        },
                    ],
                },
            ],
            Izin: IzinPenuh,
        };
        RenderUji(<HalamanDaftarKelompokPilihan {...props} />);

        expect(screen.getByText('+Rp 5.000')).toBeTruthy();
        expect(screen.getByText('Gratis')).toBeTruthy();
        expect(screen.getByText('Keju parut 20 g')).toBeTruthy();
        expect(screen.getByText('Opsional, maks 3 · dipakai 4 produk')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Ubah kelompok Topping' }));
        fireEvent.click(screen.getByRole('button', { name: 'Simpan kelompok pilihan' }));
        expect(kirimanForm[0]?.url).toBe('/kelola/kelompok-pilihan/KP-TOP');
    });

    it('kosong dan konfirmasi hapus', () => {
        render(<HalamanDaftarKelompokPilihan KelompokPilihan={[]} Izin={IzinPenuh} />);
        expect(screen.getByText(/Belum ada kelompok pilihan/)).toBeTruthy();
    });
});

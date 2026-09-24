import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarDaftarHarga from '@/Halaman/Kelola/DaftarHarga/Daftar';
import HalamanDaftarKategori from '@/Halaman/Kelola/Kategori/Daftar';
import HalamanDaftarKelompokPilihan from '@/Halaman/Kelola/KelompokPilihan/Daftar';
import HalamanDaftarSatuan from '@/Halaman/Kelola/Satuan/Daftar';

import { BuatHasilTabel, IzinPenuh } from './DataUjiKatalog';
import { AturHalamanUji, RenderUji, tiruanRouter } from './TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('./TiruanInertia')).TiruanInertia);

describe('Master katalog: formulir di dialog, hapus/nonaktifkan lewat konfirmasi (F-03)', () => {
    beforeEach(() => AturHalamanUji());
    afterEach(() => cleanup());

    it('kategori: formulir tambah tampil di dialog dan Batal menutupnya', () => {
        render(<HalamanDaftarKategori Kategori={[]} Izin={IzinPenuh} />);

        expect(screen.queryByRole('dialog')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Tambah kategori' }));
        expect(screen.getByRole('dialog', { name: 'Tambah kategori' })).toBeTruthy();
        expect(screen.getByRole('form', { name: 'Tambah kategori' })).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('kategori: router.delete baru dipanggil setelah konfirmasi', () => {
        render(
            <HalamanDaftarKategori
                Kategori={[
                    {
                        Uuid: 'K9',
                        Nama: 'Camilan',
                        Jalur: 'Camilan',
                        Kedalaman: 1,
                        UuidInduk: null,
                        JumlahProduk: 0,
                        Urutan: 0,
                    },
                ]}
                Izin={IzinPenuh}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Hapus kategori Camilan' }));
        expect(screen.getByRole('alertdialog', { name: 'Hapus kategori Camilan?' })).toBeTruthy();
        expect(tiruanRouter.delete).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Hapus kategori' }));
        expect(tiruanRouter.delete).toHaveBeenCalledWith('/kelola/kategori/K9', { preserveScroll: true });
    });

    it('satuan: batal di konfirmasi tidak menghapus', () => {
        render(
            <HalamanDaftarSatuan
                Satuan={[
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

        fireEvent.click(screen.getByRole('button', { name: 'Hapus satuan Porsi' }));
        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(tiruanRouter.delete).not.toHaveBeenCalled();
    });

    it('kelompok pilihan: konfirmasi menyebut jumlah produk, lalu menghapus', () => {
        RenderUji(
            <HalamanDaftarKelompokPilihan
                KelompokPilihan={[
                    {
                        Uuid: 'KP-GULA',
                        Nama: 'Level gula',
                        MinimalPilih: '1',
                        MaksimalPilih: '1',
                        Urutan: '0',
                        Wajib: true,
                        JumlahProduk: 6,
                        Pilihan: [
                            {
                                Uuid: 'PL-N',
                                Nama: 'Normal',
                                Harga: '0.00',
                                Aktif: true,
                                UuidProdukBahan: null,
                                Jumlah: '',
                                NamaProdukBahan: null,
                                SimbolSatuanBahan: null,
                            },
                        ],
                    },
                ]}
                Izin={IzinPenuh}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Hapus kelompok Level gula' }));
        expect(screen.getByText('Kelompok ini dilepas dari 6 produk. Transaksi lama tidak berubah.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Ya, hapus kelompok' }));
        expect(tiruanRouter.delete).toHaveBeenCalledWith('/kelola/kelompok-pilihan/KP-GULA', { preserveScroll: true });
    });

    it('daftar harga: nonaktifkan lewat konfirmasi, aktifkan langsung', () => {
        window.history.replaceState({}, '', '/kelola/daftar-harga');
        RenderUji(
            <HalamanDaftarDaftarHarga
                DaftarHarga={BuatHasilTabel([
                    {
                        Uuid: 'DH-1',
                        Nama: 'Harga GoFood',
                        Aktif: true,
                        NamaOutlet: null,
                        Kanal: 'Online',
                        LabelKanal: 'Online',
                        TierPelanggan: null,
                        MulaiPada: null,
                        SelesaiPada: null,
                        Prioritas: 10,
                        JumlahProduk: 3,
                    },
                    {
                        Uuid: 'DH-2',
                        Nama: 'Harga grosir',
                        Aktif: false,
                        NamaOutlet: null,
                        Kanal: null,
                        LabelKanal: null,
                        TierPelanggan: 'GROSIR',
                        MulaiPada: null,
                        SelesaiPada: null,
                        Prioritas: 0,
                        JumlahProduk: 0,
                    },
                ])}
                Outlet={[]}
                Kanal={[]}
                ZonaWaktu="WIB"
                Izin={IzinPenuh}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Aktifkan Harga grosir' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/daftar-harga/DH-2/aktifkan',
            {},
            { preserveScroll: true },
        );

        fireEvent.click(screen.getByRole('button', { name: 'Nonaktifkan Harga GoFood' }));
        expect(tiruanRouter.post).toHaveBeenCalledTimes(1);
        fireEvent.click(screen.getByRole('button', { name: 'Nonaktifkan daftar' }));
        expect(tiruanRouter.post).toHaveBeenLastCalledWith(
            '/kelola/daftar-harga/DH-1/nonaktifkan',
            {},
            { preserveScroll: true },
        );
    });
});

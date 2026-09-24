import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanPajak from '@/Halaman/Kelola/PanduanAwal/Pajak';
import HalamanProdukPanduan from '@/Halaman/Kelola/PanduanAwal/Produk';
import { AturHalamanUji, kirimanForm } from '@/Komponen/Katalog/TiruanInertia';
import type { PropsPajak, PropsProdukPanduan } from '@/Tipe/PanduanAwal';

import { BuatProgresContoh } from './DataUjiPanduan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

function BuatPropsPajak(ubah: Partial<PropsPajak> = {}): PropsPajak {
    return {
        Progres: BuatProgresContoh({ ProfilUsaha: 'Selesai', Sektor: 'Selesai' }),
        Pkp: false,
        Kota: { Kode: '3404', Nama: 'Kab. Sleman' },
        Nilai: { PungutPbjt: true, BiayaLayananAktif: false, PersenBiayaLayanan: '0', HargaTermasukPajak: true },
        SudahDikonfirmasi: false,
        TarifPbjt: null,
        TarifPpn: null,
        KelompokPajak: [],
        AlasanUsulan: [],
        ...ubah,
    };
}

function BuatPropsProduk(ubah: Partial<PropsProdukPanduan> = {}): PropsProdukPanduan {
    return {
        Progres: BuatProgresContoh({ ProfilUsaha: 'Selesai', Sektor: 'Selesai', Pajak: 'Selesai' }),
        AdaTemplate: true,
        ProdukContoh: [
            { Nama: 'Kopi susu', NamaKategori: 'Kopi', Harga: '18000.00', KodeSatuan: 'C62', SudahAda: false },
            { Nama: 'Teh manis', NamaKategori: 'Non-kopi', Harga: '8000.00', KodeSatuan: 'C62', SudahAda: false },
            { Nama: 'Americano', NamaKategori: 'Kopi', Harga: '15000.00', KodeSatuan: 'C62', SudahAda: true },
        ],
        Kategori: [],
        Produk: [],
        JumlahProduk: 0,
        BatasSku: { Batas: null, Terpakai: 1 },
        ...ubah,
    };
}

describe('Langkah 3 Pajak (F-01): pilihan harga termasuk pajak memakai RadioGroup', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/panduan-awal/pajak'));
    afterEach(() => cleanup());

    it('radio "Belum termasuk pajak" mengubah HargaTermasukPajak yang dikirim', () => {
        render(<HalamanPajak {...BuatPropsPajak()} />);

        const termasuk = screen.getByRole('radio', { name: /Sudah termasuk pajak/ });
        const belum = screen.getByRole('radio', { name: /Belum termasuk pajak/ });
        expect(termasuk.getAttribute('aria-checked')).toBe('true');
        expect(screen.getByRole('radiogroup', { name: 'Harga jual di menu' })).toBeTruthy();

        fireEvent.click(belum);
        expect(belum.getAttribute('aria-checked')).toBe('true');
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pengaturan pajak' }));

        expect(kirimanForm[0]).toEqual({
            metode: 'post',
            url: '/kelola/panduan-awal/pajak',
            data: { PungutPbjt: true, BiayaLayananAktif: false, PersenBiayaLayanan: '0', HargaTermasukPajak: false },
        });
    });
});

describe('Langkah 4 Produk (F-01): centang produk contoh memakai Checkbox', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/panduan-awal/produk'));
    afterEach(() => cleanup());

    it('"Pilih semua" menjadi indeterminate saat sebagian dipilih; produk yang sudah ada tidak bisa dicentang', () => {
        render(<HalamanProdukPanduan {...BuatPropsProduk()} />);

        const semua = screen.getByRole('checkbox', { name: 'Pilih semua' });
        expect(semua.getAttribute('aria-checked')).toBe('true');
        expect(screen.queryByRole('checkbox', { name: 'Pilih Americano' })).toBeNull();
        expect(screen.getByText('2 dari 2 produk contoh dipilih.')).toBeTruthy();

        fireEvent.click(screen.getByRole('checkbox', { name: 'Pilih Teh manis' }));
        expect(semua.getAttribute('aria-checked')).toBe('mixed');
        expect(screen.getByText('1 dari 2 produk contoh dipilih.')).toBeTruthy();

        fireEvent.click(semua);
        expect(semua.getAttribute('aria-checked')).toBe('true');
        fireEvent.click(semua);
        expect(semua.getAttribute('aria-checked')).toBe('false');
        expect((screen.getByRole('button', { name: 'Tambahkan produk contoh' }) as HTMLButtonElement).disabled).toBe(
            true,
        );
    });

    it('kuota paket terlampaui: peringatan tampil dan tombol tambah nonaktif', () => {
        render(<HalamanProdukPanduan {...BuatPropsProduk({ BatasSku: { Batas: 2, Terpakai: 1 } })} />);

        expect(screen.getByText(/Sisa kuota paket 1 SKU/)).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Tambahkan produk contoh' }) as HTMLButtonElement).disabled).toBe(
            true,
        );
    });
});

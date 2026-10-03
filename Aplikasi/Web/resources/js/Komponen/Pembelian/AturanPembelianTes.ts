import { describe, expect, it } from 'vitest';

import type { ProdukPembelian } from '@/Tipe/Pembelian';

import { BuatBarisDariProduk, BuatUrlCariProdukPembelian } from './AturanPembelian';

const Produk: ProdukPembelian = {
    Uuid: '01J9PRD0000000000000000001',
    Nama: 'Minyak Goreng Sawit Bening Kemasan Pouch 2 Liter',
    Sku: 'MYK-2L',
    Pelacakan: 'Tidak',
    SimbolSatuan: 'pcs',
    BolehDesimal: false,
    Satuan: [
        { Uuid: '01J9SAT0000000000000000012', Simbol: 'dus', Nama: 'Dus', Konversi: '12.0000', DefaultBeli: true },
        { Uuid: '01J9SAT0000000000000000001', Simbol: 'pcs', Nama: 'Pcs', Konversi: '1', DefaultBeli: false },
    ],
};

describe('AturanPembelian: harga beli terakhir (audit kemudahan pakai #21)', () => {
    it('belum pernah dibeli: harga kosong, satuan beli bawaan terpilih', () => {
        const baris = BuatBarisDariProduk({ ...Produk, HargaBeliTerakhir: null });
        expect(baris.Harga).toBe('');
        expect(baris.UuidProdukSatuan).toBe('01J9SAT0000000000000000012');
    });

    it('harga terakhir per pcs: satuan pcs terpilih dan harga terisi tanpa nol berlebih', () => {
        const baris = BuatBarisDariProduk({
            ...Produk,
            HargaBeliTerakhir: { Harga: '31500.00', Konversi: '1.0000', Tanggal: '2026-09-30', DariPemasokIni: true },
        });
        expect(baris.UuidProdukSatuan).toBe('01J9SAT0000000000000000001');
        expect(baris.Harga).toBe('31500');
    });

    it('harga terakhir per dus dengan sen: dus terpilih, dua desimal dipertahankan', () => {
        const baris = BuatBarisDariProduk({
            ...Produk,
            HargaBeliTerakhir: { Harga: '378000.50', Konversi: '12', Tanggal: '2026-09-30', DariPemasokIni: false },
        });
        expect(baris.UuidProdukSatuan).toBe('01J9SAT0000000000000000012');
        expect(baris.Harga).toBe('378000.5');
    });

    it('satuan pembelian terakhir sudah tidak ada: harga tidak diisi supaya tidak salah satuan', () => {
        const baris = BuatBarisDariProduk({
            ...Produk,
            HargaBeliTerakhir: { Harga: '700000.00', Konversi: '24', Tanggal: '2026-09-30', DariPemasokIni: true },
        });
        expect(baris.Harga).toBe('');
        expect(baris.UuidProdukSatuan).toBe('01J9SAT0000000000000000012');
    });

    it('URL pencarian membawa pemasok bila dipilih', () => {
        expect(BuatUrlCariProdukPembelian('minyak', 'G1', 'P1')).toBe(
            '/kelola/pembelian/produk/cari?kata=minyak&batas=20&gudang=G1&pemasok=P1',
        );
        expect(BuatUrlCariProdukPembelian('minyak', null)).toBe('/kelola/pembelian/produk/cari?kata=minyak&batas=20');
    });
});

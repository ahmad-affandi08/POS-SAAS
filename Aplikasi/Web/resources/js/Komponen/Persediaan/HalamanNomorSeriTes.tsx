import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanNomorSeri from '@/Halaman/Kelola/Persediaan/NomorSeri';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import type { PropsNomorSeri, UnitNomorSeri } from '@/Tipe/Persediaan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const unitTerjual: UnitNomorSeri = {
    Uuid: '01K5SERI00000000000000TERJUAL',
    Nomor: 'IMEI-356938035643809',
    UuidProduk: '01K5PRD0000000000000PONSEL01',
    NamaProduk: 'Ponsel Android 8/256 GB Hitam',
    Sku: 'HP-8256',
    Status: 'Terjual',
    LabelStatus: 'Terjual',
    NamaGudang: null,
    NomorPenjualan: 'INV/SLB/261007/POS-001-0012',
    TanggalJual: '2026-10-07',
};

const unitTersedia: UnitNomorSeri = {
    ...unitTerjual,
    Uuid: '01K5SERI0000000000000TERSEDIA',
    Nomor: 'IMEI-356938035643810',
    Status: 'Tersedia',
    LabelStatus: 'Tersedia',
    NamaGudang: 'Toko',
    NomorPenjualan: null,
    TanggalJual: null,
};

function Props(perubahan: Partial<PropsNomorSeri> = {}): PropsNomorSeri {
    return { Saring: { Cari: '', Unit: '' }, Hasil: [], Detail: null, BatasHasil: 50, ...perubahan };
}

describe('Kelola/Persediaan/NomorSeri (F-05h)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/persediaan/nomor-seri');
        tiruanRouter.get.mockClear();
    });
    afterEach(() => cleanup());

    it('awal: ajakan mencari; kirim pencarian ke server dengan query cari', () => {
        RenderUji(<HalamanNomorSeri {...Props()} />);
        expect(screen.getByText(/Masukkan nomor seri atau IMEI/)).toBeTruthy();

        fireEvent.change(screen.getByLabelText('Nomor seri / IMEI'), { target: { value: ' 3569380 ' } });
        fireEvent.click(screen.getByRole('button', { name: 'Cari' }));
        expect(tiruanRouter.get).toHaveBeenCalledWith(
            '/kelola/persediaan/nomor-seri',
            { cari: '3569380' },
            expect.anything(),
        );
    });

    it('hasil: status & keterangan jual; tanpa hasil ada pesan kosong; riwayat satu unit tampil berurutan', () => {
        RenderUji(
            <HalamanNomorSeri
                {...Props({
                    Saring: { Cari: '3569380', Unit: unitTerjual.Uuid },
                    Hasil: [unitTerjual, unitTersedia],
                    Detail: {
                        Unit: unitTerjual,
                        Riwayat: [
                            {
                                Tanggal: '2026-09-24',
                                Jenis: 'Stok awal',
                                Arah: 'Masuk',
                                NomorDokumen: 'SA-0001',
                                NamaGudang: 'Toko',
                            },
                            {
                                Tanggal: '2026-10-07',
                                Jenis: 'Penjualan',
                                Arah: 'Keluar',
                                NomorDokumen: 'INV/SLB/261007/POS-001-0012',
                                NamaGudang: 'Toko',
                            },
                        ],
                    },
                })}
            />,
        );
        expect(screen.getByRole('heading', { name: 'Hasil pencarian (2)' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Riwayat IMEI-356938035643809' })).toBeTruthy();
        expect(screen.getByText('Stok awal (masuk stok)')).toBeTruthy();
        expect(screen.getByText('Penjualan (keluar stok)')).toBeTruthy();
        expect(screen.getByText('SA-0001')).toBeTruthy();
        cleanup();

        RenderUji(<HalamanNomorSeri {...Props({ Saring: { Cari: 'XYZ', Unit: '' } })} />);
        expect(screen.getByText('Nomor seri tidak ditemukan.')).toBeTruthy();
    });
});

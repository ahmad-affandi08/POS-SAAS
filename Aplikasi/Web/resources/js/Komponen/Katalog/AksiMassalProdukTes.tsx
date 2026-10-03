import { fireEvent, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AksiMassalProduk from '@/Komponen/Katalog/AksiMassalProduk';
import { RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { PilihOpsi } from '@/Pengujian/InteraksiPilihan';
import type { BarisProduk } from '@/Tipe/Katalog';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

function Baris(uuid: string, nama: string): BarisProduk {
    return {
        Uuid: uuid,
        Nama: nama,
        Sku: null,
        Jenis: 'Stok',
        LabelJenis: 'Barang stok',
        NamaKategori: null,
        Merek: null,
        HargaDasar: '35000.00',
        SimbolSatuan: 'pcs',
        JumlahVarian: 0,
        TampilDiPos: true,
        DiubahPada: null,
        Status: 'Aktif',
        UrlGambarKecil: null,
    };
}

const Konteks = {
    terpilih: [
        Baris('01J9PRD0000000000000000001', 'Kopi Susu Gula Aren Literan'),
        Baris('01J9PRD0000000000000000002', 'Teh Melati Botol 1 Liter'),
    ],
    semuaHasil: false,
    total: 2,
    keadaan: { cari: '', urut: [], halaman: 1, perHalaman: 25, saring: {} },
    bersihkan: vi.fn(),
};

describe('Aksi massal produk (audit kemudahan pakai #19)', () => {
    beforeEach(() => {
        tiruanRouter.post.mockClear();
    });

    it('arsipkan & sembunyikan dari kasir mengirim Uuid terpilih; pindah kategori butuh kategori', () => {
        RenderUji(
            <AksiMassalProduk
                konteks={Konteks}
                kategori={[{ Uuid: '01J9KTG0000000000000000001', Jalur: 'Minuman › Literan' }]}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Arsipkan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/produk/massal',
            {
                Aksi: 'Arsipkan',
                Uuid: ['01J9PRD0000000000000000001', '01J9PRD0000000000000000002'],
                UuidKategori: null,
            },
            expect.anything(),
        );

        expect((screen.getByRole('button', { name: 'Pindahkan' }) as HTMLButtonElement).disabled).toBe(true);
        PilihOpsi(screen.getByRole('combobox', { name: 'Kategori tujuan' }), '01J9KTG0000000000000000001');
        fireEvent.click(screen.getByRole('button', { name: 'Pindahkan' }));
        expect(tiruanRouter.post).toHaveBeenLastCalledWith(
            '/kelola/produk/massal',
            expect.objectContaining({ Aksi: 'Kategori', UuidKategori: '01J9KTG0000000000000000001' }),
            expect.anything(),
        );
    });
});

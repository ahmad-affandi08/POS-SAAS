import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import TabelUbahVarian from '@/Komponen/Katalog/TabelUbahVarian';
import { RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { UbahNilai } from '@/Pengujian/InteraksiPilihan';
import type { BarisVarian } from '@/Tipe/Katalog';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Varian = (uuid: string, nama: string, barcode: string | null): BarisVarian => ({
    Uuid: uuid,
    Nama: nama,
    Sku: null,
    Atribut: [],
    HargaDasar: '75000.00',
    Barcode: barcode,
    Status: 'Aktif',
});

describe('Tabel ubah varian (audit kemudahan pakai #18)', () => {
    it('isi harga sama untuk semua lalu simpan hanya baris yang berubah; barcode yang sudah ada terkunci', () => {
        RenderUji(
            <TabelUbahVarian
                uuidProduk="01J9PRD0000000000000000000"
                bolehUbahHarga
                varian={[
                    Varian('01J9PRD0000000000000000001', 'Kaos S', '8991234500011'),
                    Varian('01J9PRD0000000000000000002', 'Kaos M', null),
                ]}
            />,
        );
        expect((screen.getByLabelText('Barcode Kaos S') as HTMLInputElement).disabled).toBe(true);
        UbahNilai(screen.getByLabelText('Harga untuk semua varian'), '79000');
        fireEvent.click(screen.getByRole('button', { name: 'Isi sama untuk semua' }));
        UbahNilai(screen.getByLabelText('Barcode Kaos M'), '8991234500028');
        fireEvent.click(screen.getByRole('button', { name: 'Simpan perubahan varian' }));
        expect(tiruanRouter.put).toHaveBeenCalledWith(
            '/kelola/produk/01J9PRD0000000000000000000/varian',
            {
                Baris: [
                    { Uuid: '01J9PRD0000000000000000001', Harga: '79000', Barcode: null },
                    { Uuid: '01J9PRD0000000000000000002', Harga: '79000', Barcode: '8991234500028' },
                ],
            },
            expect.anything(),
        );
    });
});

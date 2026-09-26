import { cleanup, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import HalamanKompatibilitasPerangkatPublik from '@/Halaman/Publik/KompatibilitasPerangkat';
import { RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import type { BarisKompatibilitas } from '@/Tipe/Kompatibilitas';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

function Baris(
    isi: Partial<BarisKompatibilitas> & Pick<BarisKompatibilitas, 'Uuid' | 'Jenis' | 'Nama'>,
): BarisKompatibilitas {
    return {
        Sambungan: null,
        Status: 'Kompatibel',
        LabelStatus: 'Kompatibel',
        StatusOtomatis: 'Kompatibel',
        StatusManual: null,
        Catatan: null,
        JumlahPerangkat: 2,
        JumlahTenant: 2,
        JumlahLolos: 2,
        JumlahGagal: 0,
        TerakhirDiujiPada: null,
        DisegarkanPada: null,
        ...isi,
    };
}

describe('Halaman publik kompatibilitas perangkat (v1.98)', () => {
    afterEach(() => cleanup());

    it('perangkat & printer dipisah; status bertulisan, sambungan printer, catatan tim tampil', () => {
        RenderUji(
            <HalamanKompatibilitasPerangkatPublik
                Baris={[
                    Baris({ Uuid: '01K5A00000000000000000001', Jenis: 'Perangkat', Nama: 'SUNMI V2s' }),
                    Baris({
                        Uuid: '01K5A00000000000000000002',
                        Jenis: 'Printer',
                        Nama: 'RPP02N',
                        Sambungan: 'BluetoothKlasik',
                        Status: 'Tersertifikasi',
                        LabelStatus: 'Tersertifikasi',
                        StatusManual: 'Tersertifikasi',
                        Catatan: 'Lolos uji lab dengan firmware 2.1',
                    }),
                ]}
            />,
        );
        expect(screen.getByRole('heading', { name: 'Perangkat & printer yang didukung' })).toBeTruthy();
        expect(screen.getAllByText('SUNMI V2s').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Tersertifikasi').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Bluetooth').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Lolos uji lab dengan firmware 2.1').length).toBeGreaterThan(0);
        expect(screen.getAllByText('2 usaha').length).toBeGreaterThan(0);
    });
});

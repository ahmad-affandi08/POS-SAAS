import { cleanup, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanMetodePembayaran from '@/Halaman/Kelola/PanduanAwal/MetodePembayaran';
import { AturHalamanUji, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import type { PropsMetodePembayaranPanduan } from '@/Tipe/PanduanAwal';

import { BuatProgresContoh } from './DataUjiPanduan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

/*
 * Audit kemudahan pakai: halaman Metode pembayaran tetap punya rumah setelah panduan selesai (Pengaturan › Kasir &
 * struk) — tampil di tata letak back-office biasa, bukan sebagai langkah panduan.
 */
function BuatProps(selesaiPada: string | null): PropsMetodePembayaranPanduan {
    return {
        Progres: { ...BuatProgresContoh(), SelesaiPada: selesaiPada },
        MetodePembayaran: [],
        JenisTersedia: [{ Nilai: 'QrisStatis', Label: 'QRIS statis' }],
        Bank: [],
        BatasGambarQris: { UkuranMaksimalKb: 1024, Ekstensi: ['png'] },
        GerbangPembayaran: { Aktif: false, Penyedia: null },
    };
}

beforeEach(() => AturHalamanUji({}, '/kelola/panduan-awal/metode-pembayaran'));
afterEach(cleanup);

describe('Halaman metode pembayaran', () => {
    it('panduan sudah selesai: tampil sebagai halaman Pengaturan, tanpa tombol langkah panduan', () => {
        RenderUji(<HalamanMetodePembayaran {...BuatProps('2026-10-01T01:00:00Z')} />);

        expect(screen.getAllByText('Metode pembayaran').length).toBeGreaterThan(0);
        expect(screen.queryByRole('button', { name: 'Lanjutkan' })).toBeNull();
    });

    it('panduan belum selesai: tetap langkah panduan dengan tombol Lanjutkan', () => {
        RenderUji(<HalamanMetodePembayaran {...BuatProps(null)} />);

        expect(screen.getByRole('button', { name: 'Lanjutkan' })).not.toBeNull();
    });
});

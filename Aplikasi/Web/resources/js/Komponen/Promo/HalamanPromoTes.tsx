import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarPromo from '@/Halaman/Kelola/Promo/Daftar';
import HalamanFormulirPromo from '@/Halaman/Kelola/Promo/Formulir';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import type { BarisPromo } from '@/Tipe/Promo';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const HappyHour: BarisPromo = {
    Uuid: '01K5PROMO00000000000000001',
    Kode: 'HAPPY-HOUR',
    Nama: 'Happy hour 2 kopi Rp 30.000',
    JenisAksi: 'BundelHargaTetap',
    LabelAksi: 'Bundel harga tetap',
    Prioritas: 10,
    Eksklusif: false,
    MulaiPada: '2026-09-30T17:00:00Z',
    SelesaiPada: '2026-12-31T17:00:00Z',
    Kuota: 1000,
    KuotaTerpakai: 125,
    Status: 'Aktif',
    JumlahPakai: 125,
    TotalDiskon: '1250000.00',
    Definisi: null,
};

const opsi = {
    OpsiOutlet: [{ Nilai: '01K5OUTLET0000000000000001', Label: 'Solo' }],
    OpsiTier: [{ Nilai: 'GOLD', Label: 'Gold (GOLD)' }],
    OpsiKategori: [{ Nilai: '01K5KATEGORI00000000000001', Label: 'Minuman › Kopi' }],
    OpsiKanal: [
        { Nilai: 'MakanDiTempat', Label: 'Makan di tempat' },
        { Nilai: 'BawaPulang', Label: 'Bawa pulang' },
    ],
};

describe('Halaman promo (F-16c)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/promo');
        window.history.replaceState({}, '', '/kelola/promo');
    });
    afterEach(() => cleanup());

    it('daftar: pemakaian/kuota & total potongan tampil; tanpa fitur tampil ajakan paket; tambah hanya untuk pengelola', () => {
        RenderUji(
            <HalamanDaftarPromo
                Promo={[HappyHour]}
                ModeResolusi="Terbaik"
                FiturAktif={false}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Mesin promo tersedia di paket Pro ke atas')).toBeTruthy();
        expect(screen.getAllByText('125 / 1.000').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Rp 1.250.000').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Tambah promo' })[0]?.getAttribute('href')).toBe(
            '/kelola/promo/buat',
        );

        cleanup();
        RenderUji(<HalamanDaftarPromo Promo={[]} ModeResolusi="PrioritasKetat" FiturAktif Izin={{ Kelola: false }} />);
        expect(screen.queryByRole('link', { name: 'Tambah promo' })).toBeNull();
        expect(screen.getByText(/Promo dipakai urut prioritas/)).toBeTruthy();
    });

    it('formulir: diskon persen kategori tertentu khusus tier GOLD dikirim sebagai POST', () => {
        RenderUji(<HalamanFormulirPromo Promo={null} FiturAktif {...opsi} />);
        fireEvent.change(screen.getByLabelText('Kode promo'), { target: { value: 'gold10' } });
        fireEvent.change(screen.getByLabelText('Nama promo'), { target: { value: 'Member Gold 10% kopi' } });
        fireEvent.change(screen.getByLabelText('Persen diskon'), { target: { value: '10' } });
        fireEvent.click(screen.getByLabelText('Gold (GOLD)'));
        fireEvent.click(screen.getByRole('button', { name: 'Simpan promo' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/promo',
            expect.objectContaining({
                Kode: 'GOLD10',
                Nama: 'Member Gold 10% kopi',
                JenisAksi: 'DiskonPersenItem',
                Persen: '10',
                Tier: ['GOLD'],
                JenisKondisi: 'Semua',
                UuidKondisi: [],
                Jumlah: null,
                BatasPerTransaksi: null,
            }),
            expect.anything(),
        );
    });
});

import { act, cleanup, fireEvent, screen, waitFor } from '@testing-library/react';
import { PackageIcon } from 'lucide-react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { tiruanRouter, AturHalamanUji } from '@/Komponen/Katalog/TiruanInertia';
import { RenderDenganKueri } from '@/Pengujian/RenderKueri';

import PencarianCepat, { CocokkanHalaman, type HalamanPencarian, type SumberPencarian } from './PencarianCepat';
import { SaringMenuTerlihat, SusunPencarian } from './TataLetakAplikasi';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const halaman: HalamanPencarian[] = [
    { label: 'Beranda', href: '/kelola', grup: null },
    { label: 'Produk', href: '/kelola/produk', grup: 'Produk' },
    { label: 'Pemasok', href: '/kelola/pembelian/pemasok', grup: 'Pembelian' },
];

const sumber: SumberPencarian[] = [
    {
        id: 'produk',
        label: 'Produk',
        alamat: '/kelola/produk',
        ikon: PackageIcon,
        AmbilHasil: (b) => ({ judul: String(b.Nama), keterangan: null, href: `/kelola/produk/${String(b.Uuid)}` }),
    },
];

const panggilanFetch: string[] = [];

beforeEach(() => {
    AturHalamanUji();
    panggilanFetch.length = 0;
    vi.stubGlobal(
        'fetch',
        vi.fn((url: string) => {
            panggilanFetch.push(url);

            return Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ Data: [{ Uuid: 'P1', Nama: 'Teh Tarik' }], Meta: { Total: 1 } }),
            });
        }),
    );
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('Pencarian cepat di kepala halaman', () => {
    it('Ctrl+K membuka palet; halaman disaring per kata (label atau grup)', () => {
        RenderDenganKueri(<PencarianCepat halaman={halaman} sumber={sumber} />);

        fireEvent.keyDown(window, { key: 'k', ctrlKey: true });
        expect(screen.getByRole('dialog')).toBeTruthy();

        fireEvent.change(screen.getByLabelText('Kata pencarian'), { target: { value: 'pembelian' } });
        expect(screen.getByRole('option', { name: /Pemasok/ })).toBeTruthy();
        expect(screen.queryByRole('option', { name: /Beranda/ })).toBeNull();
        expect(CocokkanHalaman(halaman[1] as HalamanPencarian, 'PRO')).toBe(true);
    });

    it('data dicari ke endpoint TabelData setelah jeda, memilih hasil membuka detailnya', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        RenderDenganKueri(<PencarianCepat halaman={halaman} sumber={sumber} />);

        fireEvent.click(screen.getByRole('button', { name: 'Pencarian cepat' }));
        fireEvent.change(screen.getByLabelText('Kata pencarian'), { target: { value: 'teh' } });
        expect(panggilanFetch).toHaveLength(0);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(350);
        });
        await waitFor(() => expect(screen.getByRole('option', { name: /Teh Tarik/ })).toBeTruthy());
        expect(panggilanFetch[0]).toBe('/kelola/produk?cari=teh');

        fireEvent.click(screen.getByRole('option', { name: /Teh Tarik/ }));
        expect(tiruanRouter.visit).toHaveBeenCalledWith('/kelola/produk/P1');
    });

    it('halaman & sumber data mengikuti menu yang boleh dilihat (izin)', () => {
        const tanpaIzin = SusunPencarian(SaringMenuTerlihat({ Pemilik: false, Izin: [] }));
        expect(tanpaIzin.sumber.map((s) => s.id)).not.toContain('produk');
        expect(tanpaIzin.halaman.some((h) => h.href === '/kelola')).toBe(true);

        const denganProduk = SusunPencarian(SaringMenuTerlihat({ Pemilik: false, Izin: ['produk.lihat'] }));
        expect(denganProduk.sumber.map((s) => s.id)).toContain('produk');
        expect(denganProduk.halaman.find((h) => h.href === '/kelola/produk')?.grup).toBe('Produk');
    });
});

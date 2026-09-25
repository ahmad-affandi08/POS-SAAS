import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanTutupBuku, { AmbilStatusPeriode } from '@/Halaman/Kelola/Akuntansi/TutupBuku';
import { AturHalamanUji, kirimanForm, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { BukaMenu } from '@/Pengujian/InteraksiRadix';
import type { BarisPeriodeAkuntansi } from '@/Tipe/Akuntansi';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const periode: BarisPeriodeAkuntansi[] = [
    {
        Periode: '2026-10',
        Label: 'Oktober 2026',
        Terkunci: false,
        DikunciPada: null,
        DikunciOleh: null,
        Berjalan: true,
        ShiftBelumDitutup: 0,
    },
    {
        Periode: '2026-09',
        Label: 'September 2026',
        Terkunci: false,
        DikunciPada: null,
        DikunciOleh: null,
        Berjalan: false,
        ShiftBelumDitutup: 2,
    },
    {
        Periode: '2026-08',
        Label: 'Agustus 2026',
        Terkunci: true,
        DikunciPada: '2026-09-05T03:05:00Z',
        DikunciOleh: 'Sari Akuntan',
        Berjalan: false,
        ShiftBelumDitutup: 0,
    },
];

describe('Tutup buku (F-15)', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/akuntansi/tutup-buku'));
    afterEach(() => cleanup());

    it('status periode: berjalan, terbuka dengan shift belum ditutup, terkunci', () => {
        expect(periode.map((p) => AmbilStatusPeriode(p).teks)).toEqual([
            'Berjalan',
            '2 shift belum ditutup',
            'Terkunci',
        ]);
        RenderUji(<HalamanTutupBuku Periode={periode} Izin={{ Kelola: true }} />);
        expect(screen.getAllByText('September 2026').length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Sari Akuntan/).length).toBeGreaterThan(0);
    });

    it('kunci periode lewat dialog konfirmasi yang menyebut shift belum ditutup', () => {
        RenderUji(<HalamanTutupBuku Periode={periode} Izin={{ Kelola: true }} />);
        BukaMenu(screen.getAllByRole('button', { name: 'Aksi periode September 2026' })[0] as HTMLElement);
        fireEvent.click(screen.getByRole('menuitem', { name: 'Kunci periode' }));
        expect(screen.getByText(/Masih ada 2 shift yang belum ditutup/)).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Kunci periode' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/akuntansi/tutup-buku/2026-09/kunci',
            {},
            expect.anything(),
        );

        cleanup();
        RenderUji(<HalamanTutupBuku Periode={periode} Izin={{ Kelola: true }} />);
        BukaMenu(screen.getAllByRole('button', { name: 'Aksi periode Agustus 2026' })[0] as HTMLElement);
        fireEvent.click(screen.getByRole('menuitem', { name: 'Buka kunci periode' }));
        fireEvent.change(screen.getByLabelText('Alasan membuka kunci'), {
            target: { value: 'Faktur pemasok Agustus baru datang' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Buka kunci periode' }));
        expect(kirimanForm.at(-1)).toMatchObject({
            metode: 'post',
            url: '/kelola/akuntansi/tutup-buku/2026-08/buka-kunci',
            data: { Alasan: 'Faktur pemasok Agustus baru datang' },
        });
    });

    it('tanpa izin kelola tidak ada aksi baris', () => {
        RenderUji(<HalamanTutupBuku Periode={periode} Izin={{ Kelola: false }} />);
        expect(screen.queryByRole('button', { name: 'Aksi periode September 2026' })).toBeNull();
    });
});

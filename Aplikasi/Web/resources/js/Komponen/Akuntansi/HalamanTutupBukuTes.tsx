import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanTutupBuku, { AmbilStatusPeriode, AmbilStatusTahun } from '@/Halaman/Kelola/Akuntansi/TutupBuku';
import { AturHalamanUji, kirimanForm, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { BukaMenu } from '@/Pengujian/InteraksiRadix';
import type { BarisPeriodeAkuntansi, BarisTahunBuku } from '@/Tipe/Akuntansi';

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
        TahunDitutup: false,
    },
    {
        Periode: '2026-09',
        Label: 'September 2026',
        Terkunci: false,
        DikunciPada: null,
        DikunciOleh: null,
        Berjalan: false,
        ShiftBelumDitutup: 2,
        TahunDitutup: false,
    },
    {
        Periode: '2026-08',
        Label: 'Agustus 2026',
        Terkunci: true,
        DikunciPada: '2026-09-05T03:05:00Z',
        DikunciOleh: 'Sari Akuntan',
        Berjalan: false,
        ShiftBelumDitutup: 0,
        TahunDitutup: false,
    },
];

const tahun: BarisTahunBuku[] = [
    {
        Tahun: 2025,
        BulanTerkunci: 12,
        Ditutup: false,
        DitutupPada: null,
        DitutupOleh: null,
        NomorJurnal: null,
        UuidJurnal: null,
    },
    {
        Tahun: 2024,
        BulanTerkunci: 12,
        Ditutup: true,
        DitutupPada: '2025-01-20T03:00:00Z',
        DitutupOleh: 'Sari Akuntan',
        NomorJurnal: 'JU/2024/12/0099',
        UuidJurnal: '01JJURNALPENUTUP2024000000',
    },
    {
        Tahun: 2023,
        BulanTerkunci: 5,
        Ditutup: false,
        DitutupPada: null,
        DitutupOleh: null,
        NomorJurnal: null,
        UuidJurnal: null,
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
        RenderUji(<HalamanTutupBuku Periode={periode} Tahun={tahun} Izin={{ Kelola: true }} />);
        expect(screen.getAllByText('September 2026').length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Sari Akuntan/).length).toBeGreaterThan(0);
    });

    it('kunci periode lewat dialog konfirmasi yang menyebut shift belum ditutup', () => {
        RenderUji(<HalamanTutupBuku Periode={periode} Tahun={tahun} Izin={{ Kelola: true }} />);
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
        RenderUji(<HalamanTutupBuku Periode={periode} Tahun={tahun} Izin={{ Kelola: true }} />);
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
        RenderUji(<HalamanTutupBuku Periode={periode} Tahun={tahun} Izin={{ Kelola: false }} />);
        expect(screen.queryByRole('button', { name: 'Aksi periode September 2026' })).toBeNull();
    });

    it('tutup tahun: status, aksi hanya untuk tahun 12 bulan terkunci, konfirmasi mengirim tahun', () => {
        expect(tahun.map((t) => AmbilStatusTahun(t).teks)).toEqual([
            'Siap ditutup',
            'Ditutup',
            '5 dari 12 bulan terkunci',
        ]);
        RenderUji(<HalamanTutupBuku Periode={periode} Tahun={tahun} Izin={{ Kelola: true }} />);
        expect(screen.getAllByText('JU/2024/12/0099').length).toBeGreaterThan(0);
        expect(screen.queryByRole('button', { name: 'Aksi tahun 2024' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Aksi tahun 2023' })).toBeNull();
        BukaMenu(screen.getAllByRole('button', { name: 'Aksi tahun 2025' })[0] as HTMLElement);
        fireEvent.click(screen.getByRole('menuitem', { name: 'Tutup tahun' }));
        expect(screen.getByText(/dipindahkan ke Laba Ditahan/)).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Tutup tahun' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/akuntansi/tutup-buku/tahun/2025/tutup',
            {},
            expect.anything(),
        );
    });

    it('bulan di tahun yang sudah ditutup tidak punya aksi buka kunci', () => {
        const ditutup = periode.map((p) => ({ ...p, TahunDitutup: p.Terkunci }));
        RenderUji(<HalamanTutupBuku Periode={ditutup} Tahun={tahun} Izin={{ Kelola: true }} />);
        expect(screen.queryByRole('button', { name: 'Aksi periode Agustus 2026' })).toBeNull();
    });
});

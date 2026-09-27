import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import KartuKodeAktivasi from './KartuKodeAktivasi';

afterEach(cleanup);

const kode = {
    UuidPerangkat: '0198f0c2-3f6a-7a12-9c31-5b2d4e6f8a10',
    Kode: 'A7K9M2QT',
    NamaPerangkat: 'Kasir Depan',
    KodePerangkat: 'JKT1-K02',
    QrSvg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    KedaluwarsaPada: '2026-09-27T10:15:00+07:00',
};

describe('KartuKodeAktivasi (F-02b BR-02.3)', () => {
    it('menampilkan kode apa adanya tanpa tanda hubung', () => {
        render(<KartuKodeAktivasi kode={kode} />);

        // Server memang menormalkan tanda hubung, tetapi yang tampil harus persis sama dengan isi QR
        // dan dengan yang diketik di aplikasi kasir supaya tidak membingungkan.
        expect(screen.getByText('A7K9M2QT')).toBeTruthy();
        expect(screen.queryByText('A7K9-M2QT')).toBeNull();
    });

    it('tombol salin menyalin kode tanpa tanda hubung lalu berubah menjadi konfirmasi', async () => {
        const TulisTeks = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText: TulisTeks } });

        render(<KartuKodeAktivasi kode={kode} />);
        fireEvent.click(screen.getByRole('button', { name: /Salin kode/ }));

        expect(TulisTeks).toHaveBeenCalledWith('A7K9M2QT');
        await waitFor(() => expect(screen.getByRole('button', { name: /Kode tersalin/ })).toBeTruthy());
    });

    it('menyebut pemindaian QR sekaligus batasnya di Windows', () => {
        render(<KartuKodeAktivasi kode={kode} />);

        // Pemindai (mobile_scanner) hanya ada di Android & iOS, jadi janji "pindai" harus disertai
        // keterangan bahwa di Windows kodenya diketik.
        expect(screen.getByText(/pindai QR ini atau ketik/i)).toBeTruthy();
        expect(screen.getByText(/Windows tidak punya pemindai/i)).toBeTruthy();
    });
});

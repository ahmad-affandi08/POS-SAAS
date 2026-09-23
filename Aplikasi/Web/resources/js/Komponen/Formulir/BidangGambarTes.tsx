import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import BidangGambar, { PeriksaBerkasGambar } from './BidangGambar';

function BuatBerkas(nama: string, ukuranByte: number, jenis = 'image/png'): File {
    const berkas = new File(['x'], nama, { type: jenis });
    Object.defineProperty(berkas, 'size', { value: ukuranByte });

    return berkas;
}

describe('BidangGambar (logo & QRIS, F-01)', () => {
    afterEach(() => cleanup());

    it('memeriksa format dan ukuran sebelum unggah', () => {
        expect(PeriksaBerkasGambar(BuatBerkas('qris.PNG', 500 * 1024), ['png', 'jpg'], 2048)).toBeNull();
        expect(PeriksaBerkasGambar(BuatBerkas('qris.pdf', 1024, 'application/pdf'), ['png', 'jpg'], 2048)).toContain(
            'tidak didukung',
        );
        expect(PeriksaBerkasGambar(BuatBerkas('logo.png', 3 * 1024 * 1024), ['png'], 1024)).toContain('melebihi batas');
    });

    it('menampilkan petunjuk format & ukuran, menolak berkas terlalu besar dengan pesan yang diumumkan', () => {
        const SaatBerubah = vi.fn();
        render(
            <BidangGambar
                label="Gambar QRIS"
                berkas={null}
                saatBerubah={SaatBerubah}
                ukuranMaksimalKb={2048}
                ekstensi={['png', 'jpg', 'jpeg', 'webp']}
            />,
        );

        expect(screen.getByText(/Format png, jpg, jpeg, webp, maksimal 2 MB\./)).toBeTruthy();

        const input = screen.getByLabelText('Gambar QRIS');
        fireEvent.change(input, { target: { files: [BuatBerkas('qris.png', 5 * 1024 * 1024)] } });

        expect(SaatBerubah).not.toHaveBeenCalled();
        expect(screen.getByText(/melebihi batas 2 MB/).closest('[aria-live]')).toBeTruthy();
        expect(input.getAttribute('aria-invalid')).toBe('true');

        fireEvent.change(input, { target: { files: [BuatBerkas('qris.png', 100 * 1024)] } });

        expect(SaatBerubah).toHaveBeenCalledTimes(1);
    });

    it('gambar tersimpan bisa dihapus bila diizinkan', () => {
        const SaatHapus = vi.fn();
        render(
            <BidangGambar
                label="Logo usaha"
                berkas={null}
                saatBerubah={() => undefined}
                tautanSaatIni="/kelola/panduan-awal/profil-usaha/logo"
                saatHapusSaatIni={SaatHapus}
                labelHapus="Hapus logo"
                ukuranMaksimalKb={1024}
                ekstensi={['png']}
            />,
        );

        expect(screen.getByRole('img', { name: 'Logo usaha saat ini' }).getAttribute('src')).toBe(
            '/kelola/panduan-awal/profil-usaha/logo',
        );

        fireEvent.click(screen.getByRole('button', { name: 'Hapus logo' }));

        expect(SaatHapus).toHaveBeenCalled();
    });
});

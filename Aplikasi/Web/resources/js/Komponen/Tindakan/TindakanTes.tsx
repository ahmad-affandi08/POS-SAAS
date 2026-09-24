import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';

import DialogFormulir from './DialogFormulir';
import DialogKonfirmasi from './DialogKonfirmasi';
import MenuAksiBaris from './MenuAksiBaris';
import { BukaMenu, PasangTiruanDom } from './TiruanDom';

beforeAll(() => PasangTiruanDom());
afterEach(() => cleanup());

describe('MenuAksiBaris', () => {
    it('tidak dirender bila tidak ada aksi yang boleh', () => {
        const { container } = render(<MenuAksiBaris label="Aksi untuk Rina" aksi={[]} />);

        expect(container.innerHTML).toBe('');
    });

    it('membuka menu, aksi bahaya dipisah & ditandai destructive, pilihan memanggil saatPilih', () => {
        const Ubah = vi.fn();
        const Cabut = vi.fn();
        render(
            <MenuAksiBaris
                label="Aksi perangkat Kasir Depan"
                aksi={[
                    { label: 'Cabut', bahaya: true, saatPilih: Cabut },
                    { label: 'Ubah nama', saatPilih: Ubah },
                    { label: 'Buat kode baru', nonaktif: true, saatPilih: vi.fn() },
                ]}
            />,
        );

        BukaMenu(screen.getByRole('button', { name: 'Aksi perangkat Kasir Depan' }));
        const menu = screen.getByRole('menu');
        const item = within(menu).getAllByRole('menuitem');
        // Aksi biasa di atas, aksi bahaya terakhir setelah pemisah.
        expect(item.map((elemen) => elemen.textContent)).toEqual(['Ubah nama', 'Buat kode baru', 'Cabut']);
        expect(within(menu).getByRole('separator')).toBeTruthy();
        expect(item[2]?.getAttribute('data-variant')).toBe('destructive');
        expect(item[1]?.getAttribute('data-disabled')).not.toBeNull();

        fireEvent.click(within(menu).getByRole('menuitem', { name: 'Cabut' }));
        expect(Cabut).toHaveBeenCalledOnce();
        expect(Ubah).not.toHaveBeenCalled();
    });
});

describe('DialogKonfirmasi', () => {
    it('alertdialog berjudul & berketerangan; Batal menutup, aksi memanggil saatKonfirmasi', () => {
        const Konfirmasi = vi.fn();
        const Batal = vi.fn();
        render(
            <DialogKonfirmasi
                judul="Nonaktifkan Rina?"
                labelAksi="Nonaktifkan pengguna"
                saatKonfirmasi={Konfirmasi}
                saatBatal={Batal}
            >
                <p>Rina langsung keluar dari usaha ini.</p>
            </DialogKonfirmasi>,
        );

        const dialog = screen.getByRole('alertdialog', { name: 'Nonaktifkan Rina?' });
        expect(dialog.getAttribute('aria-describedby')).toBeTruthy();
        expect(within(dialog).getByText('Rina langsung keluar dari usaha ini.')).toBeTruthy();

        const aksi = within(dialog).getByRole('button', { name: 'Nonaktifkan pengguna' });
        expect(aksi.className).toContain('bg-destructive');
        fireEvent.click(aksi);
        expect(Konfirmasi).toHaveBeenCalledOnce();
        expect(Batal).not.toHaveBeenCalled();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Batal' }));
        expect(Batal).toHaveBeenCalledOnce();
    });

    it('saat memproses: tombol terkunci, label "Memproses…", Batal tidak menutup', () => {
        const Batal = vi.fn();
        render(
            <DialogKonfirmasi
                judul="Cabut Kasir Depan?"
                labelAksi="Cabut perangkat"
                memproses
                saatKonfirmasi={vi.fn()}
                saatBatal={Batal}
            >
                <p>Aplikasi di perangkat ini langsung tidak bisa dipakai.</p>
            </DialogKonfirmasi>,
        );

        const dialog = screen.getByRole('alertdialog');
        const aksi = within(dialog).getByRole('button', { name: 'Memproses…' });
        expect(aksi.hasAttribute('disabled')).toBe(true);
        expect(aksi.getAttribute('aria-busy')).toBe('true');
        expect(within(dialog).getByRole('button', { name: 'Batal' }).hasAttribute('disabled')).toBe(true);
        expect(Batal).not.toHaveBeenCalled();
    });
});

describe('DialogFormulir', () => {
    it('jenis dialog: dialog berjudul tanpa tombol tutup berbahasa Inggris; Esc memanggil saatTutup', () => {
        const Tutup = vi.fn();
        render(
            <DialogFormulir judul="Tambah merek" saatTutup={Tutup}>
                <form aria-label="Formulir merek" />
            </DialogFormulir>,
        );

        const dialog = screen.getByRole('dialog', { name: 'Tambah merek' });
        expect(within(dialog).getByRole('form', { name: 'Formulir merek' })).toBeTruthy();
        expect(within(dialog).queryByText('Close')).toBeNull();

        fireEvent.keyDown(dialog, { key: 'Escape' });
        expect(Tutup).toHaveBeenCalledOnce();
    });

    it('jenis konfirmasi memakai alertdialog; jenis panel memakai panel samping', () => {
        const tampilanAwal = render(
            <DialogFormulir
                jenis="konfirmasi"
                judul="Tangguhkan tenant"
                keterangan={<p>POS terkunci.</p>}
                saatTutup={vi.fn()}
            >
                <p>isi</p>
            </DialogFormulir>,
        );
        expect(screen.getByRole('alertdialog', { name: 'Tangguhkan tenant' })).toBeTruthy();
        tampilanAwal.unmount();

        render(
            <DialogFormulir jenis="panel" judul="Undang pengguna" keterangan="Berlaku 72 jam." saatTutup={vi.fn()}>
                <p>isi</p>
            </DialogFormulir>,
        );
        const panel = screen.getByRole('dialog', { name: 'Undang pengguna' });
        expect(panel.getAttribute('data-slot')).toBe('sheet-content');
        expect(within(panel).getByText('Berlaku 72 jam.')).toBeTruthy();
    });
});

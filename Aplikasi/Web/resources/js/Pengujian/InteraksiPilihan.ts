/**
 * Bantuan test untuk `PilihanCari` (combobox + daftar ber-cari di Popover). Bukan kode produksi.
 */
import { fireEvent } from '@testing-library/react';

function CariDaftar(pemicu: HTMLElement): HTMLElement | null {
    const id = pemicu.getAttribute('aria-controls');

    return id ? document.getElementById(id) : null;
}

/** Buka daftar pilihan (bila belum terbuka) dan kembalikan elemen daftar. */
export function BukaPilihan(pemicu: HTMLElement): HTMLElement {
    if (pemicu.getAttribute('aria-expanded') !== 'true') {
        fireEvent.click(pemicu);
    }

    const daftar = CariDaftar(pemicu);

    if (daftar === null) {
        throw new Error('Daftar pilihan tidak terbuka.');
    }

    return daftar;
}

/** Nilai (`Nilai`) semua opsi yang tampil di daftar pilihan. */
export function AmbilNilaiPilihan(pemicu: HTMLElement): string[] {
    const daftar = BukaPilihan(pemicu);
    const nilai = Array.from(daftar.querySelectorAll<HTMLElement>('[data-slot="pilihan-cari-item"]')).map(
        (item) => item.dataset.nilai ?? '',
    );
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    return nilai;
}

/** Pilih opsi ber-`Nilai` tertentu di `PilihanCari`. */
export function PilihOpsi(pemicu: HTMLElement, nilai: string): void {
    const daftar = BukaPilihan(pemicu);
    const item = Array.from(daftar.querySelectorAll<HTMLElement>('[data-slot="pilihan-cari-item"]')).find(
        (el) => el.dataset.nilai === nilai,
    );

    if (!item) {
        throw new Error(`Opsi "${nilai}" tidak ada di daftar pilihan.`);
    }

    fireEvent.click(item);
}

/** Ubah nilai kontrol apa pun: `PilihanCari` lewat daftar, isian biasa lewat peristiwa change. */
export function UbahNilai(elemen: HTMLElement, nilai: string): void {
    if (elemen.getAttribute('role') === 'combobox' && elemen.tagName === 'BUTTON') {
        PilihOpsi(elemen, nilai);

        return;
    }

    fireEvent.change(elemen, { target: { value: nilai } });
}

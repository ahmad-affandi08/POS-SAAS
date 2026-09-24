/**
 * Bantuan interaksi test untuk komponen Radix di jsdom. Bukan kode produksi.
 * Pelengkap API peramban ada di `SiapkanLingkunganUji.ts` (dipasang otomatis lewat `test.setupFiles`).
 */
import { fireEvent } from '@testing-library/react';

/** Buka DropdownMenu Radix: pemicunya bereaksi pada pointerdown tombol utama, bukan click. */
export function BukaMenu(pemicu: HTMLElement): void {
    fireEvent.pointerDown(pemicu, { button: 0, ctrlKey: false, pointerType: 'mouse' });
}

/** Pilih tab Radix: pemicunya bereaksi pada mousedown tombol utama. */
export function PilihTab(tab: HTMLElement): void {
    fireEvent.mouseDown(tab, { button: 0, ctrlKey: false });
}

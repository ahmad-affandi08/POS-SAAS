/**
 * Pelengkap jsdom untuk test komponen Radix (DropdownMenu, Dialog, Tabs). Bukan kode produksi.
 * jsdom tidak punya ResizeObserver & pointer capture yang dipakai Radix Popper/DropdownMenu.
 */
import { fireEvent } from '@testing-library/react';

export function PasangTiruanDom(): void {
    if (typeof globalThis.ResizeObserver === 'undefined') {
        globalThis.ResizeObserver = class {
            observe(): void {}
            unobserve(): void {}
            disconnect(): void {}
        };
    }
    const prototipe = window.HTMLElement.prototype as HTMLElement & {
        hasPointerCapture?: (id: number) => boolean;
        releasePointerCapture?: (id: number) => void;
        scrollIntoView?: () => void;
    };
    prototipe.hasPointerCapture ??= () => false;
    prototipe.releasePointerCapture ??= () => undefined;
    prototipe.scrollIntoView ??= () => undefined;
}

/** Buka DropdownMenu Radix: pemicunya bereaksi pada pointerdown tombol utama, bukan click. */
export function BukaMenu(pemicu: HTMLElement): void {
    fireEvent.pointerDown(pemicu, { button: 0, ctrlKey: false, pointerType: 'mouse' });
}

/** Pilih tab Radix: pemicunya bereaksi pada mousedown tombol utama. */
export function PilihTab(tab: HTMLElement): void {
    fireEvent.mouseDown(tab, { button: 0, ctrlKey: false });
}

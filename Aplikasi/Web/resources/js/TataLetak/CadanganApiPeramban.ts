/**
 * Cadangan API peramban yang dipakai komponen shadcn/ui (Radix):
 * - `matchMedia`: `SidebarProvider` (hook `useIsMobile`) di tata letak back-office & Platform Pengelola;
 * - `ResizeObserver`: `Checkbox`/`Switch`/`RadioGroup` di dalam formulir, `DropdownMenu`, `Popover`, `Tooltip`.
 *
 * Semua peramban yang didukung sudah punya keduanya, jadi di sana fungsi ini tidak mengubah apa pun. Cadangan hanya
 * dipasang di lingkungan tanpa API tersebut (jsdom saat Vitest, WebView lama) agar komponen tidak gagal dirender:
 * media query dianggap tidak cocok (tata letak desktop) dan perubahan ukuran tidak dilaporkan.
 */
export function PasangCadanganApiPeramban(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (typeof window.matchMedia !== 'function') {
        window.matchMedia = (kueri: string): MediaQueryList => ({
            matches: false,
            media: kueri,
            onchange: null,
            addEventListener: () => undefined,
            removeEventListener: () => undefined,
            addListener: () => undefined,
            removeListener: () => undefined,
            dispatchEvent: () => false,
        });
    }

    if (typeof globalThis.ResizeObserver !== 'function') {
        globalThis.ResizeObserver = class {
            observe(): void {}
            unobserve(): void {}
            disconnect(): void {}
        };
    }
}

PasangCadanganApiPeramban();

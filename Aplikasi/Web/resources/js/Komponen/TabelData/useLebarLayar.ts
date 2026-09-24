import { useSyncExternalStore } from 'react';

/** Kelas lebar layar §17.4.4: `hp` < 640px, `tablet` 640–1023px, `desktop` ≥ 1024px. */
export type LebarLayar = 'hp' | 'tablet' | 'desktop';

const KUERI_HP = '(max-width: 639px)';
const KUERI_TABLET = '(max-width: 1023px)';

function Baca(): LebarLayar {
    if (window.matchMedia(KUERI_HP).matches) {
        return 'hp';
    }

    return window.matchMedia(KUERI_TABLET).matches ? 'tablet' : 'desktop';
}

function Langgan(saatBerubah: () => void): () => void {
    const daftar = [window.matchMedia(KUERI_HP), window.matchMedia(KUERI_TABLET)];
    daftar.forEach((kueri) => kueri.addEventListener('change', saatBerubah));

    return () => daftar.forEach((kueri) => kueri.removeEventListener('change', saatBerubah));
}

export function useLebarLayar(): LebarLayar {
    return useSyncExternalStore(Langgan, Baca, () => 'desktop');
}

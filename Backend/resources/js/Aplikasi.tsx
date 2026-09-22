import './Gaya/Aplikasi.css';

import { createInertiaApp } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { StrictMode, type ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

import { BuatKlienKueri } from './Pustaka/KlienKueri';

const NamaAplikasi = import.meta.env.VITE_APP_NAME ?? 'POS SaaS';
const klienKueri = BuatKlienKueri();
const daftarHalaman = import.meta.glob<{ default: ComponentType }>('./Halaman/**/*.tsx');

async function MuatHalaman(nama: string): Promise<ComponentType> {
    const MuatModul = daftarHalaman[`./Halaman/${nama}.tsx`];

    if (!MuatModul) {
        throw new Error(`Halaman Inertia tidak ditemukan: ${nama}`);
    }

    return (await MuatModul()).default;
}

void createInertiaApp({
    title: (judul) => (judul ? `${judul} · ${NamaAplikasi}` : NamaAplikasi),
    resolve: MuatHalaman,
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <QueryClientProvider client={klienKueri}>
                    <App {...props} />
                </QueryClientProvider>
            </StrictMode>,
        );
    },
    progress: { color: 'var(--color-brand)' },
});

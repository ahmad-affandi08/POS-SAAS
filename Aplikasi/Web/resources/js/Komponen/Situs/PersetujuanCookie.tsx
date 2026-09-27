import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import {
    AdaAnalitik,
    AmbilPilihanCookie,
    CatatTampilanHalaman,
    MuatAnalitik,
    SimpanPilihanCookie,
    type PengaturanAnalitik,
    type PilihanCookie,
} from '@/Pustaka/AnalitikSitus';

/**
 * Bilah persetujuan cookie analitik (bagian B, UU PDP): tampil hanya bila konsol memasang GA4/Meta Pixel dan
 * pengunjung belum memilih. Tanpa "Terima", tidak ada skrip pihak ketiga yang dimuat.
 */
export default function PersetujuanCookie({ analitik }: { analitik: PengaturanAnalitik | undefined }) {
    // Situs dirender di peramban (tanpa SSR), jadi pilihan tersimpan bisa dibaca saat render pertama.
    const [pilihan, AturPilihan] = useState<PilihanCookie | null>(AmbilPilihanCookie);
    const ada = AdaAnalitik(analitik);

    useEffect(() => {
        if (!ada || pilihan !== 'terima') {
            return undefined;
        }

        MuatAnalitik(analitik);

        return router.on('navigate', (peristiwa) => CatatTampilanHalaman(analitik, peristiwa.detail.page.url));
    }, [ada, analitik, pilihan]);

    if (!ada || pilihan !== null) {
        return null;
    }

    const Pilih = (nilai: PilihanCookie) => {
        SimpanPilihanCookie(nilai);
        AturPilihan(nilai);
    };

    return (
        <div
            role="region"
            aria-label="Persetujuan cookie"
            className="fixed inset-x-0 bottom-0 z-50 border-t border-garis bg-permukaan px-4 py-4"
        >
            <div className="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-isi text-teks-utama">
                    Kami memakai cookie analitik untuk memahami cara situs ini dipakai. Anda boleh menolak; situs tetap
                    berfungsi normal.{' '}
                    <a href="/legal/kebijakan-privasi" className="text-brand underline">
                        Kebijakan privasi
                    </a>
                </p>
                <div className="flex shrink-0 gap-2">
                    <button
                        type="button"
                        onClick={() => Pilih('tolak')}
                        className="h-11 rounded-kontrol border border-garis-input px-4 text-label font-semibold text-teks-utama hover:bg-permukaan-sorot"
                    >
                        Tolak
                    </button>
                    <button
                        type="button"
                        onClick={() => Pilih('terima')}
                        className="h-11 rounded-kontrol bg-brand px-4 text-label font-semibold text-permukaan hover:bg-brand-gelap"
                    >
                        Terima
                    </button>
                </div>
            </div>
        </div>
    );
}

import { Link } from '@inertiajs/react';

import type { TabProduk as DataTabProduk } from '@/Tipe/Katalog';

type PropsTabProduk = { tab: DataTabProduk[]; aktif: DataTabProduk['Kunci'] };

/** Navigasi antar-halaman satu produk (Ringkasan, Harga, Pilihan, Resep, Komponen) dari `KepalaProduk.Tab`. */
export default function TabProduk({ tab, aktif }: PropsTabProduk) {
    if (tab.length <= 1) {
        return null;
    }

    return (
        <nav aria-label="Bagian produk" className="flex gap-1 overflow-x-auto border-b border-garis">
            {tab.map((item) => (
                <Link
                    key={item.Kunci}
                    href={item.Tautan}
                    aria-current={item.Kunci === aktif ? 'page' : undefined}
                    className={`-mb-px shrink-0 border-b-2 px-3 py-2 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                        item.Kunci === aktif ? 'border-brand text-teks-utama' : 'border-transparent text-teks-sekunder'
                    }`}
                >
                    {item.Label}
                </Link>
            ))}
        </nav>
    );
}

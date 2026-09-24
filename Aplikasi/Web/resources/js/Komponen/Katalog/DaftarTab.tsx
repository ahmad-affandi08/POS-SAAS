import { useId, useRef, type KeyboardEvent, type ReactNode } from 'react';

export type ItemTab<K extends string> = { Kunci: K; Label: string; AdaGalat?: boolean };

type PropsDaftarTab<K extends string> = {
    label: string;
    tab: ItemTab<K>[];
    aktif: K;
    saatPilih: (kunci: K) => void;
    /** Isi panel per kunci; panel tidak aktif disembunyikan (nilai isian tetap tersimpan). */
    panel: Record<K, ReactNode>;
};

/**
 * Tab dalam satu formulir (pola ARIA tabs): panah kiri/kanan, Home, End berpindah tab.
 * Tab dengan galat diberi teks "perlu diperbaiki" (bukan warna saja).
 */
export default function DaftarTab<K extends string>({ label, tab, aktif, saatPilih, panel }: PropsDaftarTab<K>) {
    const id = useId();
    const tombol = useRef<Record<string, HTMLButtonElement | null>>({});

    const Pindah = (peristiwa: KeyboardEvent<HTMLButtonElement>, indeks: number) => {
        const tujuan =
            peristiwa.key === 'ArrowRight'
                ? (indeks + 1) % tab.length
                : peristiwa.key === 'ArrowLeft'
                  ? (indeks - 1 + tab.length) % tab.length
                  : peristiwa.key === 'Home'
                    ? 0
                    : peristiwa.key === 'End'
                      ? tab.length - 1
                      : null;
        const item = tujuan === null ? undefined : tab[tujuan];

        if (item) {
            peristiwa.preventDefault();
            saatPilih(item.Kunci);
            tombol.current[item.Kunci]?.focus();
        }
    };

    return (
        <div className="flex flex-col gap-4">
            <div role="tablist" aria-label={label} className="flex gap-1 overflow-x-auto border-b border-garis">
                {tab.map((item, indeks) => (
                    <button
                        key={item.Kunci}
                        ref={(elemen) => {
                            tombol.current[item.Kunci] = elemen;
                        }}
                        type="button"
                        role="tab"
                        id={`${id}-tab-${item.Kunci}`}
                        aria-selected={item.Kunci === aktif}
                        aria-controls={`${id}-panel-${item.Kunci}`}
                        tabIndex={item.Kunci === aktif ? 0 : -1}
                        onClick={() => saatPilih(item.Kunci)}
                        onKeyDown={(peristiwa) => Pindah(peristiwa, indeks)}
                        className={`-mb-px shrink-0 border-b-2 px-3 py-2 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                            item.Kunci === aktif
                                ? 'border-brand text-teks-utama'
                                : 'border-transparent text-teks-sekunder'
                        }`}
                    >
                        {item.Label}
                        {item.AdaGalat ? <span className="ml-1 text-bahaya">(perlu diperbaiki)</span> : null}
                    </button>
                ))}
            </div>
            {tab.map((item) => (
                <div
                    key={item.Kunci}
                    role="tabpanel"
                    id={`${id}-panel-${item.Kunci}`}
                    aria-labelledby={`${id}-tab-${item.Kunci}`}
                    hidden={item.Kunci !== aktif}
                    className="flex flex-col gap-4"
                >
                    {panel[item.Kunci]}
                </div>
            ))}
        </div>
    );
}

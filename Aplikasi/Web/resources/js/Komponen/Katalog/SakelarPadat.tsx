import { useState } from 'react';

function BacaPreferensi(kunci: string): boolean {
    try {
        return window.localStorage.getItem(kunci) === '1';
    } catch {
        return false;
    }
}

function SimpanPreferensi(kunci: string, nilai: boolean): void {
    try {
        window.localStorage.setItem(kunci, nilai ? '1' : '0');
    } catch {
        // Penyimpanan peramban diblokir: preferensi hanya berlaku di sesi ini.
    }
}

/** Mode tabel padat untuk katalog besar. Preferensi per peramban (kenyamanan saja, bukan data). */
export function usePadatTabel(kunci: string): [boolean, (nilai: boolean) => void] {
    const kunciPenuh = `Katalog.Padat.${kunci}`;
    const [padat, AturPadat] = useState(() => BacaPreferensi(kunciPenuh));

    return [
        padat,
        (nilai: boolean) => {
            AturPadat(nilai);
            SimpanPreferensi(kunciPenuh, nilai);
        },
    ];
}

/** Kelas sel tabel sesuai kepadatan. */
export function KelasSel(padat: boolean): string {
    return padat ? 'px-2 py-1' : 'px-4 py-2';
}

type PropsSakelarPadat = { padat: boolean; saatBerubah: (nilai: boolean) => void };

/** Sakelar tampilan padat (aria-pressed) untuk tabel katalog ribuan baris. */
export default function SakelarPadat({ padat, saatBerubah }: PropsSakelarPadat) {
    return (
        <button
            type="button"
            aria-pressed={padat}
            onClick={() => saatBerubah(!padat)}
            className="h-10 rounded-kontrol border border-garis-input bg-permukaan px-3 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
            Tampilan padat: {padat ? 'aktif' : 'nonaktif'}
        </button>
    );
}

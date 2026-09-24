import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { BacaKeadaanDariUrl, BacaUrut, TulisKeadaanKeUrl } from './KeadaanUrl';
import type { KeadaanTabel, UrutKolom } from './Tipe';

const JEDA_CARI_MS = 300;

/**
 * Keadaan `TabelData` yang disinkronkan ke URL (PRD §17.4.3): bisa dibagikan, dan tombol Kembali mengembalikannya.
 * URL diganti lewat `history.replaceState` (bukan kunjungan Inertia) agar tidak memuat ulang props halaman.
 * Pencarian ditahan 300 ms; mengubah cari, saring, urut, atau ukuran halaman kembali ke halaman 1.
 */
export function useKeadaanTabel(urutBawaanTeks: string, sinkronUrl: boolean) {
    const urutBawaan = useMemo<UrutKolom[]>(() => BacaUrut(urutBawaanTeks), [urutBawaanTeks]);
    const [keadaan, AturKeadaan] = useState<KeadaanTabel>(() =>
        BacaKeadaanDariUrl(sinkronUrl ? window.location.search : '', urutBawaan),
    );
    const [teksCari, AturTeksCari] = useState(keadaan.cari);
    const penahan = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (!sinkronUrl) {
            return;
        }

        const query = TulisKeadaanKeUrl(keadaan, urutBawaan, window.location.search);
        const tujuan = `${window.location.pathname}${query === '' ? '' : `?${query}`}${window.location.hash}`;

        if (tujuan !== `${window.location.pathname}${window.location.search}${window.location.hash}`) {
            window.history.replaceState(window.history.state, '', tujuan);
        }
    }, [keadaan, sinkronUrl, urutBawaan]);

    useEffect(
        () => () => {
            if (penahan.current) {
                clearTimeout(penahan.current);
            }
        },
        [],
    );

    const Ubah = useCallback((perubahan: Partial<KeadaanTabel>, tetapHalaman = false) => {
        AturKeadaan((lama) => ({
            ...lama,
            ...perubahan,
            halaman: tetapHalaman ? (perubahan.halaman ?? lama.halaman) : 1,
        }));
    }, []);

    const AturCari = useCallback(
        (teks: string) => {
            AturTeksCari(teks);

            if (penahan.current) {
                clearTimeout(penahan.current);
            }

            penahan.current = setTimeout(() => Ubah({ cari: teks }), JEDA_CARI_MS);
        },
        [Ubah],
    );

    const AturSaring = useCallback((id: string, nilai: string) => {
        AturKeadaan((lama) => {
            const saring = Object.fromEntries(Object.entries(lama.saring).filter(([kunci]) => kunci !== id));

            if (nilai.trim() !== '') {
                saring[id] = nilai;
            }

            return { ...lama, saring, halaman: 1 };
        });
    }, []);

    const HapusSemua = useCallback(() => {
        if (penahan.current) {
            clearTimeout(penahan.current);
        }

        AturTeksCari('');
        AturKeadaan((lama) => ({ ...lama, cari: '', saring: {}, halaman: 1 }));
    }, []);

    return {
        keadaan,
        teksCari,
        urutBawaan,
        AturCari,
        AturSaring,
        HapusSemua,
        AturUrut: (urut: UrutKolom[]) => Ubah({ urut: urut.length > 0 ? urut : urutBawaan }),
        AturHalaman: (halaman: number) => Ubah({ halaman }, true),
        AturPerHalaman: (perHalaman: number) => Ubah({ perHalaman }),
    };
}

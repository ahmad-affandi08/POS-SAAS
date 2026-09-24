import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { KunciKueri } from '@/Pustaka/KunciKueri';

import { BacaKeadaanDariUrl, TulisKeadaanKeUrl } from './KeadaanUrl';
import type { HasilTabel, KeadaanTabel, UrutKolom } from './Tipe';

/** Galat HTTP saat mengambil data tabel; `status` dipakai untuk pesan (403/419/5xx). */
export class GalatDataTabel extends Error {
    constructor(public readonly status: number) {
        super(`Data tabel gagal dimuat (HTTP ${String(status)}).`);
    }
}

/** Query URL saat ini dalam bentuk ternormalisasi (sama dengan yang ditulis `TulisKeadaanKeUrl`). */
function BacaQueryUrl(urutBawaan: UrutKolom[]): string {
    return TulisKeadaanKeUrl(BacaKeadaanDariUrl(window.location.search, urutBawaan), urutBawaan);
}

export async function AmbilDataTabel<T>(alamat: string, query: string): Promise<HasilTabel<T>> {
    const respons = await fetch(query === '' ? alamat : `${alamat}?${query}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (!respons.ok) {
        throw new GalatDataTabel(respons.status);
    }

    return (await respons.json()) as HasilTabel<T>;
}

/**
 * Data `TabelData` mode server lewat TanStack Query (PRD §17.4.3). Tabel awal dari props Inertia menjadi data kueri
 * untuk keadaan URL saat halaman dimuat (tanpa kedip memuat). Saat props dimuat ulang Inertia (setelah simpan/hapus),
 * data awal baru menimpa cache dan cache keadaan lain tabel ini dibuang agar tidak basi.
 */
export function useDataTabel<T>(
    id: string,
    alamat: string,
    keadaan: KeadaanTabel,
    urutBawaan: UrutKolom[],
    awal: HasilTabel<T> | undefined,
    aktif: boolean,
) {
    const klien = useQueryClient();
    const query = TulisKeadaanKeUrl(keadaan, urutBawaan);
    // Keadaan yang dipakai server menyusun `awal` = URL saat props diterima; dihitung ulang hanya bila `awal` berganti.
    const [awalTerakhir, AturAwalTerakhir] = useState(awal);
    const [queryAwal, AturQueryAwal] = useState(() => BacaQueryUrl(urutBawaan));

    if (awal !== awalTerakhir) {
        AturAwalTerakhir(awal);
        AturQueryAwal(BacaQueryUrl(urutBawaan));
    }

    useEffect(() => {
        if (!aktif || awal === undefined) {
            return;
        }

        const kunciAwal = KunciKueri.Tabel(id, alamat, queryAwal);
        const teksKunciAwal = JSON.stringify(kunciAwal);
        klien.removeQueries({
            queryKey: KunciKueri.TabelSemua(id),
            predicate: (kueri) => JSON.stringify(kueri.queryKey) !== teksKunciAwal,
        });
        klien.setQueryData(kunciAwal, awal);
    }, [aktif, alamat, awal, id, klien, queryAwal]);

    return useQuery({
        queryKey: KunciKueri.Tabel(id, alamat, query),
        queryFn: () => AmbilDataTabel<T>(alamat, query),
        enabled: aktif,
        placeholderData: keepPreviousData,
        initialData: aktif && awal !== undefined && query === queryAwal ? awal : undefined,
        // Data awal dari props Inertia baru saja dihitung server: tidak perlu diambil ulang saat halaman dibuka.
        staleTime: 30_000,
        retry: (percobaan, galat) =>
            !(galat instanceof GalatDataTabel && [401, 403, 404, 419].includes(galat.status)) && percobaan < 2,
    });
}

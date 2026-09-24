import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import TabelData from './TabelData';

/*
 * Regresi: `TabelData` dulu mengirim `columnFilters` sebagai array baru di setiap render. TanStack menghitung ulang
 * baris tersaring & menjadwalkan reset halaman setiap kali referensinya berubah → render → array baru → putaran
 * tanpa akhir yang membekukan peramban (terlihat saat memilih tanggal di halaman yang ada tabelnya).
 */
const opsiTercatat = vi.hoisted(() => [] as { state?: { columnFilters?: unknown }; autoResetPageIndex?: boolean }[]);

vi.mock('@tanstack/react-table', async (asli) => {
    const modul = await asli<typeof import('@tanstack/react-table')>();

    return {
        ...modul,
        useReactTable: (opsi: Parameters<typeof modul.useReactTable>[0]) => {
            opsiTercatat.push(opsi as (typeof opsiTercatat)[number]);

            return modul.useReactTable(opsi);
        },
    };
});

afterEach(() => {
    cleanup();
    opsiTercatat.length = 0;
});

const data = [{ Nama: 'A' }, { Nama: 'B' }];

function Tabel() {
    return (
        <QueryClientProvider client={new QueryClient()}>
            <TabelData
                id="stabil"
                label="Stabil"
                kolom={[
                    {
                        id: 'Nama',
                        accessorKey: 'Nama',
                        header: 'Nama',
                        meta: { label: 'Nama', prioritas: 'utama', wajib: true },
                    },
                ]}
                sumber={{ mode: 'lokal', data }}
                ambilIdBaris={(b) => b.Nama}
                saring={[{ id: 'Nama', label: 'Nama', jenis: 'pilihan', opsi: [{ nilai: 'A', label: 'A' }] }]}
                kosong={{ judul: 'Kosong' }}
            />
        </QueryClientProvider>
    );
}

describe('TabelData: opsi TanStack stabil antar-render (regresi pembekuan)', () => {
    it('columnFilters berreferensi sama saat induk dirender ulang tanpa perubahan saring; reset halaman otomatis mati', () => {
        const { rerender: RenderUlang } = render(<Tabel />);
        RenderUlang(<Tabel />);
        RenderUlang(<Tabel />);

        const saring = opsiTercatat.map((o) => o.state?.columnFilters);
        expect(saring.length).toBeGreaterThanOrEqual(3);
        expect(new Set(saring).size).toBe(1);
        expect(opsiTercatat.every((o) => o.autoResetPageIndex === false)).toBe(true);
    });
});

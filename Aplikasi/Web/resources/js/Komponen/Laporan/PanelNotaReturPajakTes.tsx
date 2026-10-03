import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PanelNotaReturPajak from '@/Komponen/Laporan/PanelNotaReturPajak';
import type { RingkasanNotaReturPajak } from '@/Tipe/Laporan';

const query = { dari: '2026-10-01', sampai: '2026-10-31', outlet: '' };

function Ringkasan(ubah: Partial<RingkasanNotaReturPajak>): RingkasanNotaReturPajak {
    return {
        JumlahDiperiksa: 2,
        JumlahSiap: 1,
        TotalDpp: '300000.00',
        TotalPpn: '33000.00',
        BisaDiekspor: true,
        MasalahUmum: [],
        MasalahRetur: [{ Nomor: 'RG/PST/2610/0002', Alasan: ['Surat jalan SJ/PST/2610/0004 belum difakturkan.'] }],
        Peringatan: [],
        Retur: [
            {
                Nomor: 'RG/PST/2610/0001',
                Tanggal: '2026-10-05',
                NomorFaktur: 'FJ/PST/2609/0001',
                NomorFakturPajak: '04002600000012345',
                Pembeli: 'PT Makmur Jaya Abadi',
                Dpp: '300000.00',
                Ppn: '33000.00',
            },
        ],
        ...ubah,
    };
}

function Render(ringkasan: RingkasanNotaReturPajak) {
    vi.stubGlobal(
        'fetch',
        vi.fn(() => Promise.resolve(new Response(JSON.stringify(ringkasan), { status: 200 }))),
    );
    const klien = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(
        <QueryClientProvider client={klien}>
            <PanelNotaReturPajak query={query} />
        </QueryClientProvider>,
    );
}

describe('PanelNotaReturPajak', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
    });
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('menampilkan retur siap dengan nomor Faktur Pajak asal, retur yang tertahan, dan tautan unduh CSV', async () => {
        Render(Ringkasan({}));

        expect(await screen.findByText(/1 dari 2 retur siap/)).toBeTruthy();
        expect(screen.getByText('04002600000012345')).toBeTruthy();
        expect(screen.getByText(/belum difakturkan/)).toBeTruthy();

        const tautan = screen.getByRole('link', { name: /Unduh CSV \(1 retur\)/ });

        expect(tautan.getAttribute('href')).toContain('/kelola/laporan/pajak/nota-retur/ekspor?');
    });

    it('tanpa tautan unduh ketika belum ada retur yang siap', async () => {
        Render(Ringkasan({ BisaDiekspor: false, JumlahSiap: 0, JumlahDiperiksa: 0, MasalahRetur: [], Retur: [] }));

        expect(await screen.findByText(/Tidak ada retur grosir ber-PPN/)).toBeTruthy();
        expect(screen.queryByRole('link', { name: /Unduh CSV/ })).toBeNull();
    });
});

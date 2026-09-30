import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PanelFakturPajakCoretax from '@/Komponen/Laporan/PanelFakturPajakCoretax';
import type { RingkasanFakturPajak } from '@/Tipe/Laporan';

const query = { dari: '2026-10-01', sampai: '2026-10-31', outlet: '' };

function Ringkasan(ubah: Partial<RingkasanFakturPajak>): RingkasanFakturPajak {
    return {
        JumlahDiperiksa: 2,
        JumlahSiap: 1,
        TotalDpp: '1000000.00',
        TotalPpn: '110000.00',
        BisaDiekspor: true,
        MasalahUmum: [],
        MasalahFaktur: [{ Nomor: 'FJ/PST/2610/0002', Alasan: ['Pelanggan belum punya NPWP atau NIK yang sah.'] }],
        Peringatan: ['Ada produk tanpa Kode Barang/Jasa Coretax: dipakai kode umum 000000.'],
        Selisih: [{ Nomor: 'FJ/PST/2610/0001', SelisihDpp: '0.00', SelisihPpn: '0.01' }],
        ...ubah,
    };
}

function Render(ringkasan: RingkasanFakturPajak) {
    vi.stubGlobal(
        'fetch',
        vi.fn(() => Promise.resolve(new Response(JSON.stringify(ringkasan), { status: 200 }))),
    );
    const klien = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(
        <QueryClientProvider client={klien}>
            <PanelFakturPajakCoretax query={query} />
        </QueryClientProvider>,
    );
}

describe('PanelFakturPajakCoretax', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
    });
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('menampilkan ringkasan, faktur yang dilewati, peringatan, selisih, dan tautan unduh XML', async () => {
        Render(Ringkasan({}));

        expect(await screen.findByText(/1 dari 2 faktur siap diekspor/)).toBeTruthy();
        expect(screen.getByText(/belum punya NPWP atau NIK/)).toBeTruthy();
        expect(screen.getByText(/kode umum 000000/)).toBeTruthy();
        expect(screen.getByText(/Selisih pembulatan/)).toBeTruthy();

        const tautan = screen.getByRole('link', { name: /Unduh XML \(1 faktur\)/ });

        expect(tautan.getAttribute('href')).toContain('/kelola/laporan/pajak/faktur-keluaran/ekspor?');
        expect(tautan.getAttribute('href')).toContain('dari=2026-10-01');
    });

    it('tanpa tautan unduh dan menampilkan alasan ketika penjual belum bisa menerbitkan Faktur Pajak', async () => {
        Render(
            Ringkasan({
                BisaDiekspor: false,
                JumlahSiap: 0,
                MasalahUmum: ['Usaha Anda belum ditandai sebagai PKP.'],
                MasalahFaktur: [],
                Peringatan: [],
                Selisih: [],
            }),
        );

        expect(await screen.findByText(/belum ditandai sebagai PKP/)).toBeTruthy();
        expect(screen.queryByRole('link', { name: /Unduh XML/ })).toBeNull();
    });
});

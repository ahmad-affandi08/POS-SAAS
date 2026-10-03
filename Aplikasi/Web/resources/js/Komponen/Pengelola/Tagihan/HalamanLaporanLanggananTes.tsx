import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import HalamanLaporanLangganan, { type PropsLaporanLangganan } from '@/Halaman/Pengelola/Tagihan/Laporan';
import { IzinPengelola, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

/* P-08 langkah 7 (v4.09): laporan langganan konsol menampilkan MRR/ARR, churn, pendapatan, dan umur piutang. */

const uji = vi.hoisted(() => ({ halaman: { props: {} as Record<string, unknown>, url: '/laporan-langganan' } }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...sisa }: { href: string; children?: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
    router: { get: vi.fn(), post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
    usePage: () => uji.halaman,
}));

const bersama: PropsBersamaPengelola = {
    NamaAplikasi: 'Kasir',
    Lingkungan: 'Staging',
    Kilat: null,
    Pengguna: {
        Uuid: 'P1',
        Nama: 'Dewi Lestari',
        Email: 'dewi@contoh.id',
        KodePeran: [],
        Izin: [IzinPengelola.TagihanLihat],
    },
    PeringatanSuperAdmin: false,
    PeringatanIntegrasi: [],
    PeringatanOperasional: [],
    errors: {},
};

const props: PropsLaporanLangganan = {
    Saring: { Dari: '2026-10-01', Sampai: '2026-10-31' },
    HariMasaTenggang: 7,
    Ringkasan: {
        Pada: '2026-10-03T03:00:00Z',
        Mrr: '2450000.00',
        Arr: '29400000.00',
        PelangganBerbayar: 12,
        RataRataPerPelanggan: '204166.67',
    },
    Churn: {
        PelangganAwal: 10,
        PelangganAkhir: 12,
        PelangganBaru: 3,
        PelangganBerhenti: 1,
        PersenChurn: '10.00',
        MrrAwal: '2100000.00',
        MrrBaru: '549000.00',
        MrrBerhenti: '199000.00',
        PersenChurnMrr: '9.48',
    },
    MrrPerPaket: [{ Kunci: '2', Nama: 'Pro', Pelanggan: 8, Mrr: '1592000.00' }],
    PendapatanPerPaket: [{ Kunci: '2', Nama: 'Pro', JumlahTagihan: 4, Pendapatan: '796000.00' }],
    PendapatanPerSektor: [{ Kunci: 'FNB-RST', Nama: 'FNB-RST', JumlahTagihan: 3, Pendapatan: '597000.00' }],
    Piutang: {
        Total: '445740.00',
        Jumlah: 2,
        Umur: [
            { Kunci: 'BelumJatuhTempo', Label: 'Belum jatuh tempo', Jumlah: 1, Total: '222870.00' },
            { Kunci: 'Hari1Sampai30', Label: '1–30 hari', Jumlah: 1, Total: '222870.00' },
        ],
    },
};

afterEach(cleanup);

describe('HalamanLaporanLangganan', () => {
    it('menampilkan MRR, ARR, churn, pendapatan per paket & sektor, dan umur piutang', () => {
        uji.halaman.props = { ...bersama, ...props };
        render(
            <QueryClientProvider client={new QueryClient()}>
                <HalamanLaporanLangganan {...props} />
            </QueryClientProvider>,
        );

        expect(screen.getByText('Rp 2.450.000')).toBeTruthy();
        expect(screen.getByText('Rp 29.400.000')).toBeTruthy();
        expect(screen.getByText('10.00%')).toBeTruthy();
        expect(screen.getByText(/1 dari 10 pelanggan awal berhenti/)).toBeTruthy();
        expect(screen.getAllByText('FNB-RST').length).toBeGreaterThan(0);
        expect(screen.getByText(/Piutang langganan hari ini: Rp 445.740 \(2 tagihan\)/)).toBeTruthy();
        expect(screen.getAllByText('1–30 hari').length).toBeGreaterThan(0);
    });
});

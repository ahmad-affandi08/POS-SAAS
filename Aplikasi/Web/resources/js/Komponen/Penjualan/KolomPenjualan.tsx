import { Link } from '@inertiajs/react';

import LencanaPenjualan from '@/Komponen/Penjualan/LencanaPenjualan';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { BarisPenjualan } from '@/Tipe/Penjualan';

const alamat = '/kelola/penjualan';

/** Kolom daftar penjualan; dipakai juga daftar penjualan di detail shift. */
export const kolomPenjualan: KolomTabel<BarisPenjualan>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor',
        meta: { label: 'Nomor', prioritas: 'utama', wajib: true },
        cell: ({ row }) => (
            <Link
                href={`${alamat}/${row.original.Uuid}`}
                className="font-mono font-semibold break-all text-brand underline"
            >
                {row.original.Nomor}
            </Link>
        ),
    },
    {
        id: 'DibuatOfflinePada',
        accessorKey: 'DibuatOfflinePada',
        header: 'Waktu',
        meta: { label: 'Waktu', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => (
            <>
                <span className="block">{FormatTanggalWaktu(row.original.DibuatOfflinePada)}</span>
                <span className="block text-label text-teks-sekunder">
                    Hari bisnis {FormatTanggal(row.original.TanggalBisnis)}
                </span>
            </>
        ),
    },
    {
        id: 'Outlet',
        header: 'Outlet & kasir',
        enableSorting: false,
        meta: { label: 'Outlet & kasir', prioritas: 'rendah' },
        cell: ({ row }) => (
            <>
                <span className="block text-teks-utama">{row.original.NamaOutlet}</span>
                <span className="block text-label text-teks-sekunder">{row.original.NamaKasir}</span>
            </>
        ),
    },
    {
        id: 'Kanal',
        header: 'Kanal',
        enableSorting: false,
        meta: { label: 'Kanal', prioritas: 'rendah' },
        cell: ({ row }) => row.original.LabelKanal,
    },
    {
        id: 'Metode',
        header: 'Metode bayar',
        enableSorting: false,
        meta: { label: 'Metode bayar', prioritas: 'rendah' },
        cell: ({ row }) => row.original.Metode.join(', '),
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <LencanaPenjualan
                status={row.original.Status}
                label={row.original.LabelStatus}
                perluTinjauan={row.original.PerluTinjauan}
            />
        ),
    },
    {
        id: 'TotalAkhir',
        accessorKey: 'TotalAkhir',
        header: 'Total',
        meta: { label: 'Total', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.TotalAkhir),
    },
];

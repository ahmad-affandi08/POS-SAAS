import { Link } from '@inertiajs/react';

import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { RingkasSaldoSesi, StatusSaldoSesi } from '@/Tipe/Pelanggan';

export const AlamatSaldoSesi = '/kelola/pelanggan/saldo-sesi';

export const JenisStatusSaldoSesi: Record<StatusSaldoSesi, 'sukses' | 'netral' | 'peringatan' | 'bahaya'> = {
    Aktif: 'sukses',
    Habis: 'netral',
    Hangus: 'peringatan',
    Dibatalkan: 'netral',
};

/** Kolom bersama saldo paket sesi (F-16d bagian 2): daftar saldo sesi & bagian paket sesi di detail pelanggan. */
export function BuatKolomSaldoSesi<T extends RingkasSaldoSesi>(): KolomTabel<T>[] {
    return [
        {
            id: 'NamaPaket',
            accessorKey: 'NamaPaket',
            header: 'Paket',
            enableSorting: false,
            meta: { label: 'Paket', prioritas: 'utama', wajib: true },
            cell: ({ row: { original: s } }) => (
                <span className="flex flex-col">
                    <Link
                        href={`${AlamatSaldoSesi}/${s.Uuid}`}
                        className="font-semibold break-words text-brand underline"
                    >
                        {s.NamaPaket}
                    </Link>
                    <span className="font-mono text-keterangan break-all text-teks-sekunder">{s.NomorPenjualan}</span>
                </span>
            ),
        },
        {
            id: 'SisaSesi',
            accessorKey: 'SisaSesi',
            header: 'Sisa sesi',
            meta: { label: 'Sisa sesi', prioritas: 'utama', angka: true },
            cell: ({ row: { original: s } }) =>
                `${s.SisaSesi.toLocaleString('id-ID')} dari ${s.JumlahSesi.toLocaleString('id-ID')}`,
        },
        {
            id: 'NilaiTersisa',
            accessorKey: 'NilaiTersisa',
            header: 'Nilai tersisa',
            meta: { label: 'Nilai tersisa', prioritas: 'penting', angka: true },
            cell: ({ row }) => FormatRupiah(row.original.NilaiTersisa),
        },
        {
            id: 'TanggalBeli',
            accessorKey: 'TanggalBeli',
            header: 'Tanggal beli',
            meta: { label: 'Tanggal beli', prioritas: 'rendah', kelasSel: 'whitespace-nowrap' },
            cell: ({ row }) => FormatTanggal(row.original.TanggalBeli),
        },
        {
            id: 'BerlakuSampai',
            accessorKey: 'BerlakuSampai',
            header: 'Berlaku sampai',
            meta: { label: 'Berlaku sampai', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
            cell: ({ row }) => (row.original.BerlakuSampai ? FormatTanggal(row.original.BerlakuSampai) : 'Tanpa batas'),
        },
        {
            id: 'Status',
            header: 'Status',
            enableSorting: false,
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row: { original: s } }) => (
                <LabelStatus jenis={JenisStatusSaldoSesi[s.Status]} teks={s.LabelStatus} />
            ),
        },
    ];
}

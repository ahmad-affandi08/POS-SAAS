import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { IzinPembelian, Opsi, OpsiPemasok, StatusPembelian } from '@/Tipe/Pembelian';

import { LabelStatusPembelian } from './BagianDokumenPembelian';

/**
 * Bagian bersama halaman daftar dokumen pembelian (F-04 fase 1): kolom identitas, saring (status, pemasok, tanggal),
 * dan kerangka halaman. Semua daftar memakai `TabelData` mode server (D-16).
 */

type BarisDasar = { Uuid: string; Nomor: string; Tanggal: string; Status: StatusPembelian; LabelStatus: string };

/** Kolom nomor dokumen (tautan ke detail). */
export function KolomNomor<T extends BarisDasar>(alamat: string, label = 'Nomor'): KolomTabel<T> {
    return {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: label,
        meta: { label, prioritas: 'utama', wajib: true },
        cell: ({ row: { original: d } }) => (
            <Link href={`${alamat}/${d.Uuid}`} className="font-mono font-semibold break-all text-brand underline">
                {d.Nomor}
            </Link>
        ),
    };
}

export function KolomTanggal<T extends BarisDasar>(label = 'Tanggal'): KolomTabel<T> {
    return {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: label,
        meta: { label, prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    };
}

export function KolomStatus<T extends BarisDasar>(): KolomTabel<T> {
    return {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => <LabelStatusPembelian status={row.original.Status} label={row.original.LabelStatus} />,
    };
}

export function KolomPemasok<T extends { NamaPemasok: string | null }>(): KolomTabel<T> {
    return {
        id: 'Pemasok',
        header: 'Pemasok',
        enableSorting: false,
        meta: { label: 'Pemasok', prioritas: 'penting' },
        cell: ({ row }) => <span className="break-words">{row.original.NamaPemasok ?? 'Tanpa pemasok'}</span>,
    };
}

export function KolomUang<T>(id: string, label: string, ambil: (baris: T) => string, urut = false): KolomTabel<T> {
    return {
        id,
        header: label,
        enableSorting: urut,
        meta: { label, angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(ambil(row.original)),
    };
}

/** Saring bawaan daftar dokumen pembelian: status, pemasok, rentang tanggal. */
export function BuatSaringPembelian(opsiStatus: Opsi[], opsiPemasok: OpsiPemasok[]): DefinisiSaring[] {
    return [
        {
            id: 'Status',
            label: 'Status',
            jenis: 'pilihanBanyak',
            opsi: opsiStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        {
            id: 'Pemasok',
            label: 'Pemasok',
            jenis: 'pilihan',
            opsi: opsiPemasok.map((p) => ({ nilai: p.Uuid, label: `${p.Nama} (${p.Kode})` })),
        },
        { id: 'Tanggal', label: 'Tanggal', jenis: 'rentangTanggal' },
    ];
}

/** Tombol tautan "Buat …" (hanya bila berizin kelola). */
export function TombolBuat({ href, label, izin }: { href: string; label: string; izin: IzinPembelian }) {
    return izin.Kelola ? (
        <Button asChild className="h-10">
            <Link href={href}>{label}</Link>
        </Button>
    ) : null;
}

/** Kerangka halaman daftar: judul, keterangan, pesan hanya-lihat, galat server, lalu tabel. */
export function HalamanDaftarPembelian({
    judul,
    keterangan,
    izin,
    objek,
    children,
}: {
    judul: string;
    keterangan: string;
    izin: IzinPembelian;
    objek: string;
    children: ReactNode;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <TataLetakAplikasi judul={judul}>
            <p className="max-w-3xl text-isi text-teks-sekunder">{keterangan}</p>
            {!izin.Kelola ? <PesanHanyaLihat izin="pembelian.kelola" objek={objek} /> : null}
            <DaftarGalatServer galat={props.errors} />
            {children}
        </TataLetakAplikasi>
    );
}

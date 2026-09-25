import { Link } from '@inertiajs/react';

import { KolomUang } from '@/Komponen/Pembelian/DaftarPembelian';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { BarisPelunasan, PropsDaftarPelunasan } from '@/Tipe/Piutang';

import { AlamatPiutang, HalamanDaftarPiutang, LabelStatusPiutang } from '@/Komponen/Piutang/BagianPiutang';

const alamat = `${AlamatPiutang}/pelunasan`;

const kolom: KolomTabel<BarisPelunasan>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor',
        meta: { label: 'Nomor', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: p } }) => (
            <Link href={`${alamat}/${p.Uuid}`} className="font-mono font-semibold break-all text-brand underline">
                {p.Nomor}
            </Link>
        ),
    },
    {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: 'Tanggal',
        meta: { label: 'Tanggal', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    },
    {
        id: 'Pelanggan',
        header: 'Pelanggan',
        enableSorting: false,
        meta: { label: 'Pelanggan', prioritas: 'penting' },
        cell: ({ row }) => <span className="break-words">{row.original.NamaPelanggan}</span>,
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: p } }) => <LabelStatusPiutang status={p.Status} label={p.LabelStatus} />,
    },
    KolomUang('Jumlah', 'Jumlah', (p) => p.Jumlah, true),
];

/** F-12: daftar pelunasan piutang pelanggan ke akun kas/bank. */
export default function HalamanDaftarPelunasan({ Pelunasan, OpsiStatus, Izin }: PropsDaftarPelunasan) {
    const saring: DefinisiSaring[] = [
        {
            id: 'Status',
            label: 'Status',
            jenis: 'pilihanBanyak',
            opsi: OpsiStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
    ];
    const tombol = Izin.Kelola ? (
        <Button asChild className="h-8 pointer-coarse:h-11">
            <Link href={`${alamat}/buat`}>Terima pelunasan</Link>
        </Button>
    ) : null;

    return (
        <HalamanDaftarPiutang
            judul="Pelunasan piutang"
            keterangan="Pembayaran dari pelanggan untuk penjualan tempo. Satu pelunasan boleh untuk beberapa penjualan, penuh atau sebagian."
            izin={Izin}
            objek="pelunasan piutang"
        >
            <TabelData
                id="piutang-pelunasan"
                label="Daftar pelunasan piutang"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Pelunasan }}
                ambilIdBaris={(p) => p.Uuid}
                urutBawaan="-Tanggal"
                cari="Cari nomor pelunasan"
                saring={saring}
                alamatDetail={(p) => `${alamat}/${p.Uuid}`}
                aksiAlat={tombol}
                kosong={{
                    ilustrasi: 'Pelanggan',
                    judul: 'Belum ada pelunasan piutang.',
                }}
            />
        </HalamanDaftarPiutang>
    );
}

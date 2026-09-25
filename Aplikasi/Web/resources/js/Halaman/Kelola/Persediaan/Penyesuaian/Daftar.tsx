import { Link, usePage } from '@inertiajs/react';

import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import { LabelStatusDokumen } from '@/Komponen/Persediaan/Dokumen/KomponenDokumen';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { FormatLabelGudang, FormatNilai } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisDaftarPenyesuaianStok, PropsDaftarPenyesuaianStok } from '@/Tipe/DokumenPersediaan';

export const AlamatPenyesuaian = '/kelola/persediaan/penyesuaian';

const kolom: KolomTabel<BarisDaftarPenyesuaianStok>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor',
        meta: { label: 'Nomor', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: p } }) => (
            <Link
                href={`${AlamatPenyesuaian}/${p.Uuid}`}
                className="font-mono font-semibold break-all text-brand underline"
            >
                {p.Nomor ?? 'Draf tanpa nomor'}
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
        id: 'Gudang',
        header: 'Lokasi stok',
        enableSorting: false,
        meta: { label: 'Lokasi stok', prioritas: 'penting' },
        cell: ({ row: { original: p } }) => (
            <>
                <span className="block break-words text-teks-utama">{p.NamaGudang}</span>
                {p.NamaOutlet ? <span className="block text-keterangan text-teks-sekunder">{p.NamaOutlet}</span> : null}
            </>
        ),
    },
    {
        id: 'Alasan',
        header: 'Alasan',
        enableSorting: false,
        meta: { label: 'Alasan', prioritas: 'penting' },
        cell: ({ row }) => row.original.LabelAlasan,
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => <LabelStatusDokumen status={row.original.Status} label={row.original.LabelStatus} />,
    },
    {
        id: 'NilaiPerkiraan',
        accessorKey: 'NilaiPerkiraan',
        header: 'Nilai',
        meta: { label: 'Nilai (perkiraan)', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatNilai(row.original.NilaiPerkiraan),
    },
];

/** F-05b: daftar penyesuaian stok (TabelData D-16) dengan saring status, alasan, lokasi. */
export default function HalamanDaftarPenyesuaianStok({
    Penyesuaian,
    OpsiGudang,
    OpsiStatus,
    OpsiAlasan,
    Izin,
}: PropsDaftarPenyesuaianStok) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const saring: DefinisiSaring[] = [
        {
            id: 'Status',
            label: 'Status',
            jenis: 'pilihanBanyak',
            opsi: OpsiStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        {
            id: 'Alasan',
            label: 'Alasan',
            jenis: 'pilihanBanyak',
            opsi: OpsiAlasan.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        {
            id: 'Gudang',
            label: 'Lokasi stok',
            jenis: 'pilihan',
            opsi: OpsiGudang.map((g) => ({ nilai: g.Uuid, label: FormatLabelGudang(g) })),
        },
    ];
    const tombolBuat = Izin.Kelola ? (
        <Button asChild className="h-8 pointer-coarse:h-11">
            <Link href={`${AlamatPenyesuaian}/buat`}>Buat penyesuaian</Link>
        </Button>
    ) : null;

    return (
        <TataLetakAplikasi judul="Penyesuaian stok">
            <p className="max-w-2xl text-isi text-teks-sekunder">
                Catat barang rusak, hilang, kedaluwarsa, sampel, atau konsumsi internal. Penyesuaian bernilai besar
                menunggu persetujuan pengguna lain.
            </p>
            {!Izin.Kelola ? <PesanHanyaLihat izin="persediaan.kelola" objek="penyesuaian stok" /> : null}
            <DaftarGalatServer galat={props.errors} />
            <TabelData
                id="persediaan-penyesuaian"
                label="Daftar penyesuaian stok"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: AlamatPenyesuaian, awal: Penyesuaian }}
                ambilIdBaris={(p) => p.Uuid}
                urutBawaan="-Tanggal"
                cari="Cari nomor, keterangan, atau nama/SKU produk"
                saring={saring}
                alamatDetail={(p) => `${AlamatPenyesuaian}/${p.Uuid}`}
                aksiAlat={tombolBuat}
                kosong={{
                    ilustrasi: 'Stok',
                    judul: 'Belum ada penyesuaian stok.',
                    ...(tombolBuat ? {} : { aksi: <span>Minta pengelola persediaan mencatat penyesuaian.</span> }),
                }}
            />
        </TataLetakAplikasi>
    );
}

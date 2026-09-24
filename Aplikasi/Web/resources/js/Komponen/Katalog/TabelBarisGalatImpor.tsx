import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';

export type BarisGalatImpor = {
    NomorBaris: number;
    Galat: { Bidang: string; Pesan: string }[];
    Data: Record<string, string>;
};

type PropsTabelBarisGalatImpor = {
    /** Kunci unik tabel (cache & pilihan kolom). */
    id: string;
    baris: BarisGalatImpor[];
    /** Nama barang di baris berkas, mis. kolom `Nama` (produk) atau `Produk` (stok awal). */
    ambilNama: (baris: BarisGalatImpor) => string;
};

/** Baris berkas impor yang ditolak beserta masalahnya (F-03 impor produk, F-05a impor stok awal), TabelData D-16. */
export default function TabelBarisGalatImpor({ id, baris, ambilNama }: PropsTabelBarisGalatImpor) {
    const kolom: KolomTabel<BarisGalatImpor>[] = [
        {
            id: 'NomorBaris',
            accessorKey: 'NomorBaris',
            header: 'Baris',
            meta: { label: 'Nomor baris', angka: true, prioritas: 'utama', wajib: true },
        },
        {
            id: 'Nama',
            accessorFn: (b) => ambilNama(b),
            header: 'Produk',
            meta: { label: 'Produk', prioritas: 'penting', kelasSel: 'break-words' },
        },
        {
            id: 'Masalah',
            accessorFn: (b) => b.Galat.map((g) => `${g.Bidang}: ${g.Pesan}`).join(' '),
            header: 'Masalah',
            enableSorting: false,
            meta: { label: 'Masalah', prioritas: 'penting' },
            cell: ({ row }) => (
                <ul className="flex flex-col gap-0.5">
                    {row.original.Galat.map((galat) => (
                        <li key={`${galat.Bidang}-${galat.Pesan}`}>
                            <span className="font-semibold">{galat.Bidang}:</span> {galat.Pesan}
                        </li>
                    ))}
                </ul>
            ),
        },
    ];

    return (
        <TabelData
            id={id}
            label="Baris bermasalah"
            kolom={kolom}
            sumber={{ mode: 'lokal', data: baris }}
            ambilIdBaris={(b) => String(b.NomorBaris)}
            urutBawaan="NomorBaris"
            cari="Cari nama produk atau masalah"
            kosong={{ judul: 'Tidak ada baris bermasalah.' }}
        />
    );
}

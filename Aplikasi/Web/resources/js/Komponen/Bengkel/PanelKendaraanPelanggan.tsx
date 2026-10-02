import { Link } from '@inertiajs/react';
import { useState } from 'react';

import DialogKendaraan, { AlamatKendaraan } from '@/Komponen/Bengkel/DialogKendaraan';
import { AlamatPerintahKerja } from '@/Komponen/Bengkel/KolomPerintahKerja';
import Tombol from '@/Komponen/Formulir/Tombol';
import Panel from '@/Komponen/Kelola/Panel';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { Kendaraan } from '@/Tipe/Bengkel';

const kolom: KolomTabel<Kendaraan>[] = [
    {
        id: 'NomorPolisi',
        accessorKey: 'NomorPolisi',
        header: 'Nomor polisi',
        meta: { label: 'Nomor polisi', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: k } }) => (
            <Link href={`${AlamatKendaraan}/${k.Uuid}`} className="font-mono font-semibold text-brand underline">
                {k.NomorPolisi}
            </Link>
        ),
    },
    {
        id: 'Label',
        accessorKey: 'Label',
        header: 'Kendaraan',
        meta: { label: 'Kendaraan', prioritas: 'penting' },
        cell: ({ row: { original: k } }) => (k.Aktif ? k.Label : `${k.Label} (diarsipkan)`),
    },
    {
        id: 'ServisTerakhirPada',
        header: 'Servis terakhir',
        enableSorting: false,
        meta: { label: 'Servis terakhir', prioritas: 'rendah' },
        cell: ({ row: { original: k } }) =>
            k.ServisTerakhirPada === null ? 'Belum pernah' : FormatTanggal(k.ServisTerakhirPada.slice(0, 10)),
    },
];

/**
 * Kendaraan pelanggan di halaman detail pelanggan (§9.10, hanya untuk pemegang izin `bengkel.kelola`): daftar
 * kendaraannya, tambah kendaraan, dan pintasan membuat perintah kerja.
 */
export default function PanelKendaraanPelanggan({
    pelanggan,
    kendaraan,
}: {
    pelanggan: { Uuid: string; Nama: string };
    kendaraan: Kendaraan[];
}) {
    const [tambah, AturTambah] = useState(false);
    const pertama = kendaraan.find((k) => k.Aktif);

    return (
        <Panel
            judul="Kendaraan"
            aksi={
                <div className="flex flex-wrap gap-2">
                    <Tombol varian="sekunder" onClick={() => AturTambah(true)}>
                        Tambah kendaraan
                    </Tombol>
                    {pertama ? (
                        <Link
                            href={`${AlamatPerintahKerja}/buat?kendaraan=${pertama.Uuid}`}
                            className="self-center text-brand underline"
                        >
                            Buat perintah kerja
                        </Link>
                    ) : null}
                </div>
            }
        >
            <TabelData
                id="pelanggan-kendaraan"
                label={`Kendaraan ${pelanggan.Nama}`}
                kolom={kolom}
                sumber={{ mode: 'lokal', data: kendaraan }}
                ambilIdBaris={(k) => k.Uuid}
                kosong={{ judul: 'Pelanggan ini belum punya kendaraan terdaftar.' }}
            />
            {tambah ? (
                <DialogKendaraan kendaraan={null} pelanggan={pelanggan} saatTutup={() => AturTambah(false)} />
            ) : null}
        </Panel>
    );
}

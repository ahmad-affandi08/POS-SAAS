import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import Tombol from '@/Komponen/Formulir/Tombol';
import FormMitra from '@/Komponen/Pengelola/Mitra/FormMitra';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type BarisMitra = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Jenis: string;
    LabelJenis: string;
    Status: string;
    PersenKomisi: string;
    KomisiBerulang: boolean;
    JumlahTenant: number;
    KomisiTertunda: string;
};

type PropsDaftarMitra = { Mitra: BarisMitra[]; OpsiJenis: { Nilai: string; Label: string }[] };

const kolom: KolomTabel<BarisMitra>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Mitra',
        meta: { label: 'Mitra', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: m } }) => (
            <Link href={`/mitra/${m.Uuid}`} className="font-semibold text-brand underline">
                {m.Nama}
                <span className="block font-mono text-keterangan text-teks-sekunder">{m.Kode}</span>
            </Link>
        ),
    },
    {
        id: 'Jenis',
        accessorKey: 'LabelJenis',
        header: 'Jenis',
        meta: { label: 'Jenis', prioritas: 'rendah' },
    },
    {
        id: 'Komisi',
        header: 'Komisi',
        enableSorting: false,
        meta: { label: 'Komisi', angka: true, prioritas: 'penting' },
        cell: ({ row: { original: m } }) =>
            `${FormatPersen(m.PersenKomisi)}% ${m.KomisiBerulang ? 'berulang' : 'sekali'}`,
    },
    {
        id: 'JumlahTenant',
        accessorKey: 'JumlahTenant',
        header: 'Tenant rujukan',
        meta: { label: 'Tenant rujukan', angka: true, prioritas: 'penting' },
    },
    {
        id: 'KomisiTertunda',
        accessorKey: 'KomisiTertunda',
        header: 'Komisi tertunda',
        meta: { label: 'Komisi tertunda', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.KomisiTertunda),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <LabelStatus jenis={row.original.Status === 'Aktif' ? 'sukses' : 'netral'} teks={row.original.Status} />
        ),
    },
];

/** P-12 Mitra, reseller & referral: daftar mitra, rujukan, dan komisi tertunda (TabelData D-16). */
export default function HalamanDaftarMitra({ Mitra, OpsiJenis }: PropsDaftarMitra) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.MitraKelola);
    const [tambah, AturTambah] = useState(false);

    return (
        <TataLetakPengelola judul="Mitra & referral">
            {tambah ? <FormMitra mitra={null} opsiJenis={OpsiJenis} saatSelesai={() => AturTambah(false)} /> : null}
            <AksiHalaman keterangan="Tenant yang mendaftar lewat tautan mitra dalam 90 hari tercatat sebagai rujukannya. Komisi dihitung dari tagihan langganan yang lunas.">
                {bolehKelola ? <Tombol onClick={() => AturTambah(true)}>Tambah mitra</Tombol> : null}
            </AksiHalaman>
            <TabelData
                id="pengelola-mitra"
                label="Daftar mitra"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Mitra }}
                ambilIdBaris={(m) => m.Uuid}
                urutBawaan="Nama"
                cari="Cari nama atau kode mitra"
                saring={[
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'Aktif', label: 'Aktif' },
                            { nilai: 'Ditangguhkan', label: 'Ditangguhkan' },
                        ],
                    },
                ]}
                kosong={{
                    ilustrasi: true,
                    judul: 'Belum ada mitra. Tambahkan reseller atau perujuk, lalu bagikan tautan pendaftarannya.',
                }}
            />
        </TataLetakPengelola>
    );
}

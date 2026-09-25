import { Link, usePage } from '@inertiajs/react';

import { jenisLabelStatusTiket, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { HasilTabel, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant } from '@/Tipe/Organisasi';

type RingkasanTiket = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    LabelKategori: string;
    Status: StatusTiket;
    LabelStatus: string;
    DibuatPada: string;
    PesanTerakhirPada: string | null;
};

type PropsDaftar = { Tiket: HasilTabel<RingkasanTiket> };

const kolom: KolomTabel<RingkasanTiket>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor',
        meta: { label: 'Nomor', prioritas: 'penting', kelasSel: 'font-mono text-label whitespace-nowrap' },
        cell: ({ row }) => (
            <Link href={`/kelola/bantuan/${row.original.Uuid}`} className="font-semibold text-brand underline">
                {row.original.Nomor}
            </Link>
        ),
    },
    {
        id: 'Judul',
        header: 'Judul',
        enableSorting: false,
        meta: { label: 'Judul', prioritas: 'utama', wajib: true },
        cell: ({ row }) => (
            <>
                <span className="block break-words text-teks-utama">{row.original.Judul}</span>
                <span className="text-keterangan font-normal text-teks-sekunder">{row.original.LabelKategori}</span>
            </>
        ),
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <LabelStatus jenis={jenisLabelStatusTiket[row.original.Status]} teks={row.original.LabelStatus} />
        ),
    },
    {
        id: 'DibuatPada',
        accessorKey: 'DibuatPada',
        header: 'Pesan terakhir',
        meta: { label: 'Pesan terakhir', prioritas: 'rendah', kelasSel: 'text-teks-sekunder whitespace-nowrap' },
        cell: ({ row }) => FormatTanggalWaktu(row.original.PesanTerakhirPada ?? row.original.DibuatPada),
    },
];

/** Daftar tiket bantuan tenant (P-09, TabelData D-16). Bawaan: tiket yang masih terbuka. */
export default function DaftarBantuan({ Tiket }: PropsDaftar) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.BantuanTiketKelola);

    return (
        <TataLetakAplikasi judul="Bantuan">
            <p className="text-isi text-teks-sekunder">
                Ada kendala? Kirim tiket ke Tim Dukungan dan pantau balasannya di sini.
            </p>

            <TabelData
                id="kelola-bantuan"
                label="Tiket bantuan"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: '/kelola/bantuan', awal: Tiket }}
                ambilIdBaris={(tiket) => tiket.Uuid}
                urutBawaan="-DibuatPada"
                cari="Cari nomor atau judul tiket"
                saring={[
                    {
                        id: 'Keadaan',
                        label: 'Status',
                        jenis: 'pilihan',
                        nilaiBawaan: 'Terbuka',
                        opsi: [
                            { nilai: 'Terbuka', label: 'Masih terbuka' },
                            { nilai: 'Semua', label: 'Semua tiket' },
                        ],
                    },
                ]}
                alamatDetail={(tiket) => `/kelola/bantuan/${tiket.Uuid}`}
                aksiAlat={
                    bolehKelola ? (
                        <Button asChild className="h-8 pointer-coarse:h-11">
                            <Link href="/kelola/bantuan/buat">Buat tiket</Link>
                        </Button>
                    ) : null
                }
                kosong={{ judul: 'Tidak ada tiket yang masih terbuka. Buat tiket bila Anda butuh bantuan.' }}
            />
        </TataLetakAplikasi>
    );
}

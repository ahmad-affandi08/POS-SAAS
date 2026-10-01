import { Link } from '@inertiajs/react';

import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisKampanye, PropsDaftarKampanye, StatusKampanye } from '@/Tipe/Kampanye';

export const AlamatKampanye = '/kelola/pelanggan/kampanye';

export const JenisStatusKampanye: Record<StatusKampanye, 'netral' | 'sukses' | 'peringatan' | 'bahaya'> = {
    Draf: 'netral',
    Dijadwalkan: 'peringatan',
    Berjalan: 'peringatan',
    Selesai: 'sukses',
    Dibatalkan: 'bahaya',
};

const kolom: KolomTabel<BarisKampanye>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Kampanye',
        meta: { label: 'Kampanye', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: k } }) => (
            <span className="flex flex-col">
                <Link
                    href={`${AlamatKampanye}/${k.Uuid}`}
                    className="font-semibold break-words text-teks-utama underline-offset-2 hover:underline"
                >
                    {k.Nama}
                </Link>
                <span className="text-label text-teks-sekunder">{k.LabelKanal}</span>
            </span>
        ),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'utama' },
        cell: ({ row: { original: k } }) => (
            <span className="flex flex-col items-start gap-1">
                <LabelStatus jenis={JenisStatusKampanye[k.Status]} teks={k.LabelStatus} />
                {k.Status === 'Dijadwalkan' && k.DijadwalkanPada ? (
                    <span className="text-keterangan text-teks-sekunder">{FormatTanggalWaktu(k.DijadwalkanPada)}</span>
                ) : null}
            </span>
        ),
    },
    {
        id: 'JumlahPenerima',
        accessorKey: 'JumlahPenerima',
        header: 'Penerima',
        meta: { label: 'Penerima', angka: true, prioritas: 'penting' },
        cell: ({ row: { original: k } }) => (k.JumlahPenerima === 0 ? '–' : String(k.JumlahPenerima)),
    },
    {
        id: 'JumlahTerkirim',
        accessorKey: 'JumlahTerkirim',
        header: 'Terkirim',
        enableSorting: false,
        meta: { label: 'Terkirim', angka: true, prioritas: 'penting' },
        cell: ({ row: { original: k } }) =>
            k.JumlahPenerima === 0 ? '–' : `${String(k.JumlahTerkirim)} (gagal ${String(k.JumlahGagal)})`,
    },
    {
        id: 'DibuatPada',
        accessorKey: 'DibuatPada',
        header: 'Dibuat',
        meta: { label: 'Dibuat', prioritas: 'rendah', kelasSel: 'whitespace-nowrap text-teks-sekunder' },
        cell: ({ row }) => FormatTanggalWaktu(row.original.DibuatPada),
    },
];

/**
 * CRM-07 kampanye pesan: kirim promosi lewat WhatsApp/email hanya ke pelanggan yang setuju menerima promosi, bersegmen
 * RFM, tier, tag, atau ulang tahun. Dibuka dari halaman Pelanggan.
 */
export default function HalamanDaftarKampanye({ Kampanye, OpsiStatus, OpsiKanal }: PropsDaftarKampanye) {
    return (
        <TataLetakAplikasi judul="Kampanye pesan" jejak={[{ label: 'Pelanggan', href: '/kelola/pelanggan' }]}>
            <AksiHalaman
                keterangan={
                    <span className="max-w-3xl text-isi text-teks-sekunder">
                        Pesan hanya dikirim ke pelanggan yang menyetujui kabar promosi, bertahap pukul 08.00–21.00, dan
                        selalu memuat tautan berhenti berlangganan.
                    </span>
                }
            >
                <Button asChild>
                    <Link href={`${AlamatKampanye}/buat`}>Buat kampanye</Link>
                </Button>
            </AksiHalaman>

            <TabelData
                id="kampanye-pesan"
                label="Daftar kampanye pesan"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: AlamatKampanye, awal: Kampanye }}
                ambilIdBaris={(k) => k.Uuid}
                urutBawaan="-DibuatPada"
                cari="Cari nama kampanye"
                saring={[
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: OpsiStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
                    },
                    {
                        id: 'Kanal',
                        label: 'Kanal',
                        jenis: 'pilihanBanyak',
                        opsi: OpsiKanal.map((o) => ({ nilai: o.Nilai, label: o.Label })),
                    },
                ]}
                alamatDetail={(k) => `${AlamatKampanye}/${k.Uuid}`}
                kosong={{ ilustrasi: true, judul: 'Belum ada kampanye pesan.' }}
            />
        </TataLetakAplikasi>
    );
}

import { useForm, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { HasilTabel, KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type Pilihan, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Wilayah = { Kode: string; Nama: string; Tingkat: string; KodeInduk: string | null; ZonaWaktu: string };

type PropsWilayah = {
    Wilayah: HasilTabel<Wilayah>;
    PilihanTingkat: Pilihan[];
    PilihanZonaWaktu: string[];
};

function BuatKolom(labelTingkat: Map<string, string>): KolomTabel<Wilayah>[] {
    return [
        {
            id: 'Kode',
            accessorKey: 'Kode',
            header: 'Kode',
            meta: { label: 'Kode', prioritas: 'penting', kelasSel: 'font-mono text-label whitespace-nowrap' },
        },
        {
            id: 'Nama',
            accessorKey: 'Nama',
            header: 'Nama',
            meta: { label: 'Nama', prioritas: 'utama', wajib: true, kelasSel: 'text-teks-utama' },
        },
        {
            id: 'Tingkat',
            accessorKey: 'Tingkat',
            header: 'Tingkat',
            enableSorting: false,
            meta: { label: 'Tingkat', prioritas: 'penting', kelasSel: 'text-teks-sekunder' },
            cell: ({ row }) => labelTingkat.get(row.original.Tingkat) ?? row.original.Tingkat,
        },
        {
            id: 'ZonaWaktu',
            accessorKey: 'ZonaWaktu',
            header: 'Zona waktu',
            enableSorting: false,
            meta: { label: 'Zona waktu', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        },
    ];
}

/** Data wilayah resmi (P-02), TabelData D-16. Muat massal lewat perintah server `pengelola:impor-wilayah`. */
export default function HalamanWilayah({ Wilayah, PilihanTingkat, PilihanZonaWaktu }: PropsWilayah) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiWilayahKelola);
    const [sunting, AturSunting] = useState<Wilayah | 'baru' | null>(null);
    const kolom = useMemo(
        () => BuatKolom(new Map(PilihanTingkat.map((item) => [item.Nilai, item.Label]))),
        [PilihanTingkat],
    );

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah wilayah</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormWilayah
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    wilayah={sunting === 'baru' ? null : sunting}
                    pilihanTingkat={PilihanTingkat}
                    pilihanZonaWaktu={PilihanZonaWaktu}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            <TabelData
                id="pengelola-referensi-wilayah"
                label="Daftar wilayah"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: '/referensi/wilayah', awal: Wilayah }}
                ambilIdBaris={(wilayah) => wilayah.Kode}
                urutBawaan="Kode"
                cari="Cari nama atau kode wilayah"
                saring={[
                    {
                        id: 'Tingkat',
                        label: 'Tingkat',
                        jenis: 'pilihanBanyak',
                        opsi: PilihanTingkat.map((item) => ({ nilai: item.Nilai, label: item.Label })),
                    },
                ]}
                {...(bolehKelola
                    ? {
                          aksiBaris: (wilayah: Wilayah) => (
                              <DropdownMenuItem onSelect={() => AturSunting(wilayah)}>Ubah wilayah</DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{
                    judul: 'Belum ada wilayah. Muat data resmi dengan perintah server: php artisan pengelola:impor-wilayah wilayah.csv',
                }}
            />
        </TataLetakPengelola>
    );
}

type PropsFormWilayah = {
    wilayah: Wilayah | null;
    pilihanTingkat: Pilihan[];
    pilihanZonaWaktu: string[];
    saatSelesai: () => void;
};

function FormWilayah({ wilayah, pilihanTingkat, pilihanZonaWaktu, saatSelesai }: PropsFormWilayah) {
    const formulir = useForm({
        Kode: wilayah?.Kode ?? '',
        Nama: wilayah?.Nama ?? '',
        Tingkat: wilayah?.Tingkat ?? 'KabupatenKota',
        KodeInduk: wilayah?.KodeInduk ?? '',
        ZonaWaktu: wilayah?.ZonaWaktu ?? 'WIB',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (wilayah === null) {
            formulir.post('/referensi/wilayah', opsi);
        } else {
            formulir.put(`/referensi/wilayah/${encodeURIComponent(wilayah.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={wilayah === null ? 'Tambah wilayah' : `Ubah ${wilayah.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode resmi"
                    kode
                    keterangan="Provinsi 2 digit (33), kabupaten/kota 33.74. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                    galat={formulir.errors.Kode}
                    disabled={wilayah !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangPilihan
                    label="Tingkat"
                    nilai={formulir.data.Tingkat}
                    opsi={pilihanTingkat}
                    saatBerubah={(nilai) => formulir.setData('Tingkat', nilai)}
                    galat={formulir.errors.Tingkat}
                />
                <BidangTeks
                    label="Kode provinsi induk"
                    kode
                    keterangan="Kosongkan untuk provinsi."
                    nilai={formulir.data.KodeInduk}
                    saatBerubah={(nilai) => formulir.setData('KodeInduk', nilai)}
                    galat={formulir.errors.KodeInduk}
                />
                <BidangPilihan
                    label="Zona waktu"
                    nilai={formulir.data.ZonaWaktu}
                    opsi={pilihanZonaWaktu.map((zona) => ({ Nilai: zona, Label: zona }))}
                    saatBerubah={(nilai) => formulir.setData('ZonaWaktu', nilai)}
                    galat={formulir.errors.ZonaWaktu}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan wilayah
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

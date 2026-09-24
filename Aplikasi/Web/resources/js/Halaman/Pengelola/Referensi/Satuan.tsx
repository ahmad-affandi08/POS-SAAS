import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Satuan = { Kode: string; Nama: string; Simbol: string; BolehDesimal: boolean; Aktif: boolean };

const kolom: KolomTabel<Satuan>[] = [
    {
        id: 'Kode',
        accessorKey: 'Kode',
        header: 'Kode',
        meta: { label: 'Kode', prioritas: 'penting', kelasSel: 'font-mono text-label' },
    },
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Nama',
        meta: { label: 'Nama', prioritas: 'utama', wajib: true, kelasSel: 'text-teks-utama' },
    },
    {
        id: 'Simbol',
        accessorKey: 'Simbol',
        header: 'Simbol',
        meta: { label: 'Simbol', prioritas: 'penting', kelasSel: 'font-mono text-label text-teks-sekunder' },
    },
    {
        id: 'BolehDesimal',
        header: 'Jumlah desimal',
        enableSorting: false,
        meta: { label: 'Jumlah desimal', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        cell: ({ row }) => (row.original.BolehDesimal ? 'Boleh (misal 1,5)' : 'Bilangan bulat'),
    },
    {
        id: 'Aktif',
        accessorKey: 'Aktif',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <LabelStatus
                jenis={row.original.Aktif ? 'sukses' : 'netral'}
                teks={row.original.Aktif ? 'Aktif' : 'Nonaktif'}
            />
        ),
    },
];

/** Satuan standar platform, disalin ke tenant oleh template sektor (P-02), TabelData D-16. */
export default function HalamanSatuan({ Satuan }: { Satuan: Satuan[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiSatuanKelola);
    const [sunting, AturSunting] = useState<Satuan | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah satuan</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormSatuan
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    satuan={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            <TabelData
                id="pengelola-referensi-satuan"
                label="Daftar satuan standar"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Satuan }}
                ambilIdBaris={(satuan) => satuan.Kode}
                cari="Cari kode, nama, atau simbol"
                saring={[
                    {
                        id: 'Aktif',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'true', label: 'Aktif' },
                            { nilai: 'false', label: 'Nonaktif' },
                        ],
                    },
                ]}
                {...(bolehKelola
                    ? {
                          aksiBaris: (satuan: Satuan) => (
                              <DropdownMenuItem onSelect={() => AturSunting(satuan)}>Ubah satuan</DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{ judul: 'Belum ada satuan standar. Tambahkan satuan pertama, misal pcs atau kg.' }}
            />
        </TataLetakPengelola>
    );
}

function FormSatuan({ satuan, saatSelesai }: { satuan: Satuan | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Kode: satuan?.Kode ?? '',
        Nama: satuan?.Nama ?? '',
        Simbol: satuan?.Simbol ?? '',
        BolehDesimal: satuan?.BolehDesimal ?? false,
        Aktif: satuan?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (satuan === null) {
            formulir.post('/referensi/satuan', opsi);
        } else {
            formulir.put(`/referensi/satuan/${encodeURIComponent(satuan.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={satuan === null ? 'Tambah satuan' : `Ubah ${satuan.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-3" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    keterangan="Huruf besar, misal KG. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                    disabled={satuan !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangTeks
                    label="Simbol"
                    nilai={formulir.data.Simbol}
                    saatBerubah={(nilai) => formulir.setData('Simbol', nilai)}
                    galat={formulir.errors.Simbol}
                />
                <KotakCentang
                    label="Boleh jumlah desimal (misal 1,5 kg)"
                    nilai={formulir.data.BolehDesimal}
                    saatBerubah={(nilai) => formulir.setData('BolehDesimal', nilai)}
                />
                <KotakCentang
                    label="Aktif"
                    nilai={formulir.data.Aktif}
                    saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
                />
                <DialogFooter className="sm:col-span-3 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan satuan
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

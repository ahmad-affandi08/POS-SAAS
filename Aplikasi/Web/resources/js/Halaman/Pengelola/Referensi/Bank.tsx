import { useForm, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
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
import { IzinPengelola, PunyaIzin, type Pilihan, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Referensi = { Kode: string; Nama: string; Jenis: string; Aktif: boolean };

type PropsBank = { Referensi: Referensi[]; PilihanJenis: Pilihan[] };

function BuatKolom(labelJenis: Map<string, string>): KolomTabel<Referensi>[] {
    return [
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
            id: 'Jenis',
            accessorKey: 'Jenis',
            header: 'Jenis',
            meta: { label: 'Jenis', prioritas: 'penting', kelasSel: 'text-teks-sekunder' },
            cell: ({ row }) => labelJenis.get(row.original.Jenis) ?? row.original.Jenis,
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
}

/** Referensi pembayaran: bank, dompet digital, jaringan EDC, penerbit QRIS (P-02), TabelData D-16. */
export default function HalamanBank({ Referensi, PilihanJenis }: PropsBank) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiBankKelola);
    const [sunting, AturSunting] = useState<Referensi | 'baru' | null>(null);
    const kolom = useMemo(
        () => BuatKolom(new Map(PilihanJenis.map((item) => [item.Nilai, item.Label]))),
        [PilihanJenis],
    );

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah referensi</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormBank
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    referensi={sunting === 'baru' ? null : sunting}
                    pilihanJenis={PilihanJenis}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            <TabelData
                id="pengelola-referensi-bank"
                label="Daftar referensi pembayaran"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Referensi }}
                ambilIdBaris={(referensi) => referensi.Kode}
                cari="Cari kode atau nama"
                saring={[
                    {
                        id: 'Jenis',
                        label: 'Jenis',
                        jenis: 'pilihanBanyak',
                        opsi: PilihanJenis.map((item) => ({ nilai: item.Nilai, label: item.Label })),
                    },
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
                          aksiBaris: (referensi: Referensi) => (
                              <DropdownMenuItem onSelect={() => AturSunting(referensi)}>
                                  Ubah referensi
                              </DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{
                    judul: 'Belum ada referensi pembayaran. Tambahkan bank, dompet digital, jaringan EDC, atau penerbit QRIS yang bisa dipilih tenant.',
                }}
            />
        </TataLetakPengelola>
    );
}

function FormBank({
    referensi,
    pilihanJenis,
    saatSelesai,
}: {
    referensi: Referensi | null;
    pilihanJenis: Pilihan[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        Kode: referensi?.Kode ?? '',
        Nama: referensi?.Nama ?? '',
        Jenis: referensi?.Jenis ?? 'Bank',
        Aktif: referensi?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (referensi === null) {
            formulir.post('/referensi/bank', opsi);
        } else {
            formulir.put(`/referensi/bank/${encodeURIComponent(referensi.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={referensi === null ? 'Tambah referensi pembayaran' : `Ubah ${referensi.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    keterangan="Huruf besar tanpa spasi, misal BCA atau GOPAY. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                    disabled={referensi !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangPilihan
                    label="Jenis"
                    nilai={formulir.data.Jenis}
                    opsi={pilihanJenis}
                    saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                    galat={formulir.errors.Jenis}
                />
                <KotakCentang
                    label="Aktif (bisa dipilih tenant)"
                    nilai={formulir.data.Aktif}
                    saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan referensi
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

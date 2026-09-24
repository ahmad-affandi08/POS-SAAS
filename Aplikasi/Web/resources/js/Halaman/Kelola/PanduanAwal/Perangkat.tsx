import { Link, router, useForm } from '@inertiajs/react';
import { useRef, useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import KartuKodeAktivasi from '@/Komponen/Kelola/KartuKodeAktivasi';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Card } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { CekBatasPenuh, FormatBatas } from '@/Tipe/Organisasi';
import { AlamatPanduan, type PropsPerangkatPanduan } from '@/Tipe/PanduanAwal';

type StatusPerangkat = PropsPerangkatPanduan['Perangkat'][number]['Status'];

const labelStatus: Record<StatusPerangkat, { jenis: 'sukses' | 'peringatan' | 'netral'; teks: string }> = {
    Aktif: { jenis: 'sukses', teks: 'Aktif' },
    BelumDiaktifkan: { jenis: 'peringatan', teks: 'Belum diaktifkan' },
    Dicabut: { jenis: 'netral', teks: 'Dicabut' },
};

type BarisPerangkat = PropsPerangkatPanduan['Perangkat'][number];

const kolom: KolomTabel<BarisPerangkat>[] = [
    {
        id: 'Kode',
        accessorKey: 'Kode',
        header: 'Kode',
        meta: { label: 'Kode', prioritas: 'utama', wajib: true, kelasSel: 'font-mono text-label text-teks-utama' },
    },
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Nama',
        meta: { label: 'Nama', prioritas: 'penting' },
        cell: ({ row: { original: baris } }) => (
            <>
                <span className="break-words text-teks-utama">{baris.Nama}</span>
                <span className="block text-keterangan text-teks-sekunder">{baris.LabelJenis}</span>
            </>
        ),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <LabelStatus jenis={labelStatus[row.original.Status].jenis} teks={labelStatus[row.original.Status].teks} />
        ),
    },
];

/** Langkah 6 F-01: tambah perangkat kasir di outlet panduan dan aktifkan dengan kode + QR (memakai ulang F-02b). */
export default function HalamanPerangkatPanduan({
    Progres,
    Outlet,
    Perangkat,
    KodeAktivasiBaru,
    BolehKelolaPerangkat,
}: PropsPerangkatPanduan) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const [memproses, AturMemproses] = useState<string | null>(null);
    const batasPenuh = CekBatasPenuh(Outlet.BatasPerangkat);
    const formulir = useForm({ Nama: Perangkat.length === 0 ? 'Kasir 1' : '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(AlamatPanduan.Perangkat, {
            preserveScroll: true,
            onSuccess: () => formulir.setData('Nama', ''),
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    const BuatKodeBaru = (uuid: string) =>
        router.post(
            `${AlamatPanduan.Perangkat}/${uuid}/kode-aktivasi`,
            {},
            { preserveScroll: true, onStart: () => AturMemproses(uuid), onFinish: () => AturMemproses(null) },
        );

    return (
        <TataLetakPanduan progres={Progres} langkah="Perangkat" lanjut="selesaikan">
            <p className="text-isi text-teks-sekunder">
                Daftarkan HP, tablet, atau komputer yang dipakai kasir di outlet{' '}
                <span className="font-semibold text-teks-utama">{Outlet.Nama}</span>, lalu aktifkan aplikasi kasir
                dengan kode atau QR. Pemakaian:{' '}
                <span className="font-semibold text-teks-utama">{FormatBatas(Outlet.BatasPerangkat, 'perangkat')}</span>
                .
            </p>

            {KodeAktivasiBaru ? <KartuKodeAktivasi kode={KodeAktivasiBaru} /> : null}

            {!BolehKelolaPerangkat ? (
                <Pemberitahuan jenis="info" judul="Anda belum bisa menambah perangkat">
                    Minta Pemilik atau Admin menambahkan perangkat kasir.
                </Pemberitahuan>
            ) : batasPenuh ? (
                <Pemberitahuan jenis="info" judul="Batas perangkat paket tercapai">
                    {FormatBatas(Outlet.BatasPerangkat, 'perangkat')} di outlet ini. Cabut perangkat yang tidak dipakai
                    di menu Perangkat, atau tambah add-on perangkat di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    .
                </Pemberitahuan>
            ) : (
                <Card className="p-4 sm:p-6">
                    <form ref={elemenFormulir} onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                        <RingkasanGalatFormulir galat={formulir.errors} />
                        <div className="max-w-md">
                            <BidangTeks
                                label="Nama perangkat kasir"
                                nilai={formulir.data.Nama}
                                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                                galat={formulir.errors.Nama}
                                keterangan='Misal "Kasir Depan" atau "Tablet Bar".'
                                maxLength={100}
                                required
                            />
                        </div>
                        <div>
                            <Tombol type="submit" memproses={formulir.processing}>
                                Tambah perangkat kasir
                            </Tombol>
                        </div>
                    </form>
                </Card>
            )}

            <TabelData
                id="panduan-perangkat"
                label={`Perangkat di outlet ${Outlet.Nama}`}
                kolom={[
                    ...kolom,
                    {
                        id: 'Tindakan',
                        header: () => <span className="sr-only">Tindakan</span>,
                        enableSorting: false,
                        meta: { label: 'Tindakan', prioritas: 'penting', wajib: true, kelasSel: 'text-right' },
                        cell: ({ row: { original: baris } }) =>
                            BolehKelolaPerangkat && baris.Status !== 'Dicabut' ? (
                                <Tombol
                                    varian="sekunder"
                                    onClick={() => BuatKodeBaru(baris.Uuid)}
                                    memproses={memproses === baris.Uuid}
                                    disabled={memproses !== null}
                                >
                                    {baris.Status === 'Aktif' ? 'Pindahkan ke HP lain' : 'Buat kode baru'}
                                </Tombol>
                            ) : null,
                    },
                ]}
                sumber={{ mode: 'lokal', data: Perangkat }}
                ambilIdBaris={(baris) => baris.Uuid}
                urutBawaan="Kode"
                kosong={{
                    judul: 'Belum ada perangkat kasir di outlet ini. Tambahkan satu untuk mulai berjualan di aplikasi kasir.',
                }}
            />
        </TataLetakPanduan>
    );
}

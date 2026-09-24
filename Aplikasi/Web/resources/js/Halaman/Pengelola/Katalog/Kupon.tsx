import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import BidangTanggal from '@/Komponen/Pengelola/BidangTanggal';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Kupon = {
    Kode: string;
    Jenis: 'Persen' | 'Nominal';
    Nilai: string;
    DurasiBulan: number;
    Kuota: number | null;
    DaftarKodePaket: string[] | null;
    BerlakuSampai: string | null;
    Aktif: boolean;
};

type PropsKupon = { Kupon: Kupon[]; Paket: { Kode: string; Nama: string }[] };

const kolom: KolomTabel<Kupon>[] = [
    {
        id: 'Kode',
        accessorKey: 'Kode',
        header: 'Kode',
        meta: { label: 'Kode', prioritas: 'utama', wajib: true, kelasSel: 'font-mono text-label' },
    },
    {
        id: 'Diskon',
        header: 'Diskon',
        enableSorting: false,
        meta: { label: 'Diskon', angka: true, prioritas: 'penting' },
        cell: ({ row: { original: kupon } }) =>
            kupon.Jenis === 'Persen' ? `${FormatPersen(kupon.Nilai)}%` : FormatRupiah(kupon.Nilai),
    },
    {
        id: 'Ketentuan',
        header: 'Ketentuan',
        enableSorting: false,
        meta: { label: 'Ketentuan', prioritas: 'rendah', kelasSel: 'text-keterangan text-teks-sekunder' },
        cell: ({ row: { original: kupon } }) => (
            <>
                <span className="block">{kupon.DurasiBulan} bulan</span>
                <span className="block">Kuota: {kupon.Kuota ?? 'tanpa batas'}</span>
                <span className="block">Paket: {kupon.DaftarKodePaket?.join(', ') ?? 'semua'}</span>
                <span className="block">
                    Berlaku sampai: {kupon.BerlakuSampai ? FormatTanggal(kupon.BerlakuSampai) : 'tanpa batas'}
                </span>
            </>
        ),
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

/** Kupon langganan (P-04), TabelData D-16. Pemakaian dicatat saat penagihan (P-08). */
export default function HalamanKupon({ Kupon, Paket }: PropsKupon) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.KatalogKuponKelola);
    const [sunting, AturSunting] = useState<Kupon | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Katalog"
            aksi={
                bolehKelola && sunting === null ? <Tombol onClick={() => AturSunting('baru')}>Buat kupon</Tombol> : null
            }
        >
            <TabKatalog />
            {sunting !== null ? (
                <FormKupon
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    kupon={sunting === 'baru' ? null : sunting}
                    paket={Paket}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            <TabelData
                id="pengelola-katalog-kupon"
                label="Daftar kupon langganan"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Kupon }}
                ambilIdBaris={(kupon) => kupon.Kode}
                urutBawaan="Kode"
                cari="Cari kode kupon"
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
                          aksiBaris: (kupon: Kupon) => (
                              <DropdownMenuItem onSelect={() => AturSunting(kupon)}>Ubah kupon</DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{
                    judul: 'Belum ada kupon. Buat kupon untuk promo langganan, misal diskon 50% selama 3 bulan.',
                }}
            />
        </TataLetakPengelola>
    );
}

function FormKupon({
    kupon,
    paket,
    saatSelesai,
}: {
    kupon: Kupon | null;
    paket: { Kode: string; Nama: string }[];
    saatSelesai: () => void;
}) {
    const formulir = useForm<{
        Kode: string;
        Jenis: string;
        Nilai: string;
        DurasiBulan: string;
        Kuota: string;
        DaftarKodePaket: string[];
        BerlakuSampai: string;
        Aktif: boolean;
    }>({
        Kode: kupon?.Kode ?? '',
        Jenis: kupon?.Jenis ?? 'Persen',
        Nilai: kupon?.Nilai.replace(/\.00$/, '') ?? '',
        DurasiBulan: String(kupon?.DurasiBulan ?? 1),
        Kuota: kupon?.Kuota == null ? '' : String(kupon.Kuota),
        DaftarKodePaket: kupon?.DaftarKodePaket ?? [],
        BerlakuSampai: kupon?.BerlakuSampai ?? '',
        Aktif: kupon?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (kupon === null) {
            formulir.post('/katalog/kupon', opsi);
        } else {
            formulir.put(`/katalog/kupon/${encodeURIComponent(kupon.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={kupon === null ? 'Buat kupon' : `Ubah ${kupon.Kode}`}
            saatTutup={saatSelesai}
            lebar="lebar"
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    keterangan="Huruf besar/angka/tanda hubung. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                    disabled={kupon !== null}
                />
                <BidangPilihan
                    label="Jenis diskon"
                    nilai={formulir.data.Jenis}
                    opsi={[
                        { Nilai: 'Persen', Label: 'Persen (%)' },
                        { Nilai: 'Nominal', Label: 'Nominal (Rp)' },
                    ]}
                    saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                    galat={formulir.errors.Jenis}
                />
                <BidangTeks
                    label={formulir.data.Jenis === 'Persen' ? 'Diskon (%)' : 'Diskon (Rp)'}
                    inputMode="decimal"
                    nilai={formulir.data.Nilai}
                    saatBerubah={(nilai) => formulir.setData('Nilai', nilai)}
                    galat={formulir.errors.Nilai}
                />
                <BidangTeks
                    label="Durasi (bulan)"
                    inputMode="numeric"
                    nilai={formulir.data.DurasiBulan}
                    saatBerubah={(nilai) => formulir.setData('DurasiBulan', nilai)}
                    galat={formulir.errors.DurasiBulan}
                />
                <BidangTeks
                    label="Kuota pemakaian (opsional)"
                    inputMode="numeric"
                    nilai={formulir.data.Kuota}
                    saatBerubah={(nilai) => formulir.setData('Kuota', nilai)}
                    galat={formulir.errors.Kuota}
                />
                <BidangTanggal
                    label="Berlaku sampai (TTTT-BB-HH, opsional)"
                    nilai={formulir.data.BerlakuSampai}
                    saatBerubah={(nilai) => formulir.setData('BerlakuSampai', nilai)}
                    galat={formulir.errors.BerlakuSampai}
                />
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Berlaku untuk paket (kosongkan untuk semua paket)"
                        opsi={paket.map((item) => ({ nilai: item.Kode, label: item.Nama }))}
                        terpilih={formulir.data.DaftarKodePaket}
                        saatBerubah={(terpilih) => formulir.setData('DaftarKodePaket', terpilih)}
                        galat={formulir.errors.DaftarKodePaket}
                    />
                </div>
                <KotakCentang
                    label="Aktif"
                    nilai={formulir.data.Aktif}
                    saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan kupon
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

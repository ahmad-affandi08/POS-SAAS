import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisAturanKomisi, CakupanKomisi, JenisKomisi, OpsiUuidNama, PropsAturanKomisi } from '@/Tipe/Karyawan';

const alamat = '/kelola/karyawan/komisi';

/** `10.00` persen → `10%`; nominal → `Rp 5.000 / jumlah`. */
export function FormatNilaiKomisi(jenis: JenisKomisi, nilai: string): string {
    return jenis === 'Persen'
        ? `${nilai.replace(/\.00$/, '').replace('.', ',')}%`
        : `${FormatRupiah(nilai)} per jumlah`;
}

const kolom: KolomTabel<BarisAturanKomisi>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Nama aturan',
        meta: { label: 'Nama aturan', prioritas: 'utama', wajib: true },
        cell: ({ row }) => <span className="font-semibold break-words">{row.original.Nama}</span>,
    },
    {
        id: 'Sasaran',
        header: 'Berlaku untuk',
        enableSorting: false,
        meta: { label: 'Berlaku untuk', prioritas: 'penting' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="break-words">{a.NamaSasaran}</span>
                <span className="text-keterangan text-teks-sekunder">
                    {a.LabelCakupan} · {a.LevelStaf ? `level ${a.LevelStaf}` : 'semua level'}
                </span>
            </span>
        ),
    },
    {
        id: 'Nilai',
        header: 'Komisi',
        enableSorting: false,
        meta: { label: 'Komisi', prioritas: 'penting', angka: true },
        cell: ({ row }) => FormatNilaiKomisi(row.original.Jenis, row.original.Nilai),
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'rendah' },
        cell: ({ row }) => (
            <LabelStatus jenis={row.original.Status === 'Aktif' ? 'sukses' : 'netral'} teks={row.original.Status} />
        ),
    },
];

type Isian = {
    Nama: string;
    Cakupan: CakupanKomisi;
    UuidProduk: string;
    NamaProduk: string;
    UuidKategori: string;
    LevelStaf: string;
    Jenis: JenisKomisi;
    Nilai: string;
};

function FormulirAturan({
    aturan,
    opsiKategori,
    saatTutup,
}: {
    aturan: BarisAturanKomisi | null;
    opsiKategori: OpsiUuidNama[];
    saatTutup: () => void;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<Isian>({
        Nama: aturan?.Nama ?? '',
        Cakupan: aturan?.Cakupan ?? 'Semua',
        UuidProduk: aturan?.UuidProduk ?? '',
        NamaProduk: aturan?.Cakupan === 'Produk' ? aturan.NamaSasaran : '',
        UuidKategori: aturan?.UuidKategori ?? '',
        LevelStaf: aturan?.LevelStaf ?? '',
        Jenis: aturan?.Jenis ?? 'Persen',
        Nilai: (aturan?.Nilai ?? '').replace(/\.00$/, ''),
    });
    const Ubah = (ubah: Partial<Isian>) => AturIsian({ ...isian, ...ubah });
    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const data = {
            Nama: isian.Nama,
            Cakupan: isian.Cakupan,
            UuidProduk: isian.Cakupan === 'Produk' && isian.UuidProduk !== '' ? isian.UuidProduk : null,
            UuidKategori: isian.Cakupan === 'Kategori' && isian.UuidKategori !== '' ? isian.UuidKategori : null,
            LevelStaf: isian.LevelStaf.trim() === '' ? null : isian.LevelStaf.trim(),
            Jenis: isian.Jenis,
            Nilai: isian.Nilai,
        };
        const opsi = { preserveScroll: true, onSuccess: saatTutup };

        if (aturan === null) {
            router.post(alamat, data, opsi);
        } else {
            router.put(`${alamat}/${aturan.Uuid}`, data, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={aturan === null ? 'Tambah aturan komisi' : `Ubah aturan ${aturan.Nama}`}
            jenis="panel"
            galatUmum={galat.Umum}
            saatTutup={saatTutup}
        >
            <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir aturan komisi">
                <BidangTeks
                    label="Nama aturan"
                    nilai={isian.Nama}
                    saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                    galat={galat.Nama}
                    keterangan="Misal Komisi potong rambut senior."
                    maxLength={100}
                    required
                />
                <BidangPilihan
                    label="Berlaku untuk"
                    nilai={isian.Cakupan}
                    opsi={[
                        { Nilai: 'Semua', Label: 'Semua produk' },
                        { Nilai: 'Kategori', Label: 'Satu kategori' },
                        { Nilai: 'Produk', Label: 'Satu produk' },
                    ]}
                    saatBerubah={(nilai) => Ubah({ Cakupan: nilai as CakupanKomisi })}
                />
                {isian.Cakupan === 'Kategori' ? (
                    <BidangPilihan
                        label="Kategori"
                        nilai={isian.UuidKategori}
                        kosong="Pilih kategori"
                        opsi={opsiKategori.map((k) => ({ Nilai: k.Uuid, Label: k.Nama }))}
                        saatBerubah={(nilai) => Ubah({ UuidKategori: nilai })}
                        galat={galat.UuidKategori}
                    />
                ) : null}
                {isian.Cakupan === 'Produk' ? (
                    <div className="flex flex-col gap-1">
                        <PemilihProduk
                            label={isian.NamaProduk === '' ? 'Pilih produk' : `Produk: ${isian.NamaProduk} (ganti)`}
                            jenis={[]}
                            saatPilih={(produk) => Ubah({ UuidProduk: produk.Uuid, NamaProduk: produk.Nama })}
                            galat={galat.UuidProduk}
                        />
                    </div>
                ) : null}
                <BidangTeks
                    label="Level staf (opsional)"
                    nilai={isian.LevelStaf}
                    saatBerubah={(nilai) => Ubah({ LevelStaf: nilai })}
                    galat={galat.LevelStaf}
                    keterangan="Kosongkan agar berlaku untuk semua level. Aturan dengan level yang cocok lebih diutamakan."
                    maxLength={40}
                />
                <BidangPilihan
                    label="Jenis komisi"
                    nilai={isian.Jenis}
                    opsi={[
                        { Nilai: 'Persen', Label: 'Persen dari nilai penjualan (tanpa pajak)' },
                        { Nilai: 'Tetap', Label: 'Nominal per jumlah' },
                    ]}
                    saatBerubah={(nilai) => Ubah({ Jenis: nilai as JenisKomisi })}
                />
                {isian.Jenis === 'Persen' ? (
                    <BidangTeks
                        label="Persen komisi"
                        nilai={isian.Nilai}
                        saatBerubah={(nilai) => Ubah({ Nilai: nilai.replace(',', '.') })}
                        galat={galat.Nilai}
                        inputMode="decimal"
                        maxLength={6}
                    />
                ) : (
                    <BidangUang
                        label="Nominal per jumlah"
                        nilai={isian.Nilai}
                        saatBerubah={(nilai) => Ubah({ Nilai: nilai })}
                        galat={galat.Nilai}
                    />
                )}
                <div className="flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="outline" onClick={saatTutup}>
                        Batal
                    </Button>
                    <Button type="submit">Simpan aturan</Button>
                </div>
            </form>
        </DialogFormulir>
    );
}

/** F-18 EMP-04: aturan komisi (produk paling spesifik menang; level staf yang cocok diutamakan). */
export default function HalamanAturanKomisi({ Aturan, OpsiKategori, Izin }: PropsAturanKomisi) {
    const [form, AturForm] = useState<{ aturan: BarisAturanKomisi | null } | null>(null);
    const tombol = Izin.Kelola ? (
        <Button onClick={() => AturForm({ aturan: null })}>Tambah aturan komisi</Button>
    ) : null;

    return (
        <TataLetakAplikasi judul="Aturan komisi">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Kasir memilih staf yang melayani tiap baris di aplikasi kasir. Komisi dihitung dari aturan yang paling
                spesifik (produk, lalu kategori, lalu semua produk) dan dibagi rata bila beberapa staf. Perubahan aturan
                berlaku untuk penjualan berikutnya.
            </p>
            {Izin.Kelola ? <div>{tombol}</div> : <PesanHanyaLihat izin="karyawan.kelola" objek="aturan komisi" />}
            <TabelData
                id="aturan-komisi"
                label="Daftar aturan komisi"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Aturan }}
                ambilIdBaris={(a) => a.Uuid}
                urutBawaan="Nama"
                cari="Cari nama aturan"
                labelBaris={(a) => `untuk aturan ${a.Nama}`}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (a: BarisAturanKomisi) => (
                              <ItemAksiBaris
                                  aksi={[
                                      { label: 'Ubah aturan', saatPilih: () => AturForm({ aturan: a }) },
                                      a.Status === 'Aktif'
                                          ? {
                                                label: 'Arsipkan aturan',
                                                bahaya: true,
                                                saatPilih: () =>
                                                    router.post(
                                                        `${alamat}/${a.Uuid}/arsipkan`,
                                                        {},
                                                        { preserveScroll: true },
                                                    ),
                                            }
                                          : {
                                                label: 'Pulihkan aturan',
                                                saatPilih: () =>
                                                    router.post(
                                                        `${alamat}/${a.Uuid}/pulihkan`,
                                                        {},
                                                        { preserveScroll: true },
                                                    ),
                                            },
                                  ]}
                              />
                          ),
                      }
                    : {})}
                kosong={{ judul: 'Belum ada aturan komisi.', ...(tombol ? { aksi: tombol } : {}) }}
            />
            {form !== null ? (
                <FormulirAturan aturan={form.aturan} opsiKategori={OpsiKategori} saatTutup={() => AturForm(null)} />
            ) : null}
        </TataLetakAplikasi>
    );
}

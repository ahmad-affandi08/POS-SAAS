import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Badge } from '@/Komponen/Ui/badge';
import { Button } from '@/Komponen/Ui/button';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisKategoriKas, JenisKategoriKas, PropsKategoriKas } from '@/Tipe/Kasir';

const alamat = '/kelola/kasir/kategori-kas';

const kolom: KolomTabel<BarisKategoriKas>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Nama',
        meta: { label: 'Nama', prioritas: 'utama', wajib: true, kelasSel: 'font-semibold text-teks-utama' },
    },
    {
        id: 'Jenis',
        accessorKey: 'Jenis',
        header: 'Jenis',
        meta: { label: 'Jenis', prioritas: 'penting' },
        cell: ({ row }) => row.original.LabelJenis,
    },
    {
        id: 'Akun',
        accessorFn: (k) => k.Akun ?? '—',
        header: 'Akun jurnal',
        meta: { label: 'Akun jurnal', prioritas: 'rendah', kelasSel: 'font-mono text-label' },
    },
    {
        id: 'Aktif',
        accessorKey: 'Aktif',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <Badge variant={row.original.Aktif ? 'default' : 'outline'}>
                {row.original.Aktif ? 'Aktif' : 'Nonaktif'}
            </Badge>
        ),
    },
];

type IsianKategori = { Nama: string; Jenis: JenisKategoriKas; UuidAkun: string };

const opsiJenis = [
    { Nilai: 'Keluar', Label: 'Kas keluar (beban, kasbon, dsb.)' },
    { Nilai: 'Masuk', Label: 'Kas masuk (tambahan modal, pendapatan lain)' },
];

/** F-06: kategori kas masuk/keluar non-penjualan yang dipilih kasir di POS, masing-masing dengan akun jurnalnya. */
export default function HalamanKategoriKas({ Kategori, OpsiAkun }: PropsKategoriKas) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [form, AturForm] = useState<{ uuid: string | null; isian: IsianKategori } | null>(null);
    const [memproses, AturMemproses] = useState(false);

    const Buka = (kategori: BarisKategoriKas | null) =>
        AturForm({
            uuid: kategori?.Uuid ?? null,
            isian: kategori
                ? { Nama: kategori.Nama, Jenis: kategori.Jenis, UuidAkun: kategori.UuidAkun ?? '' }
                : { Nama: '', Jenis: 'Keluar', UuidAkun: '' },
        });

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        if (form === null) {
            return;
        }

        const opsi = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => AturMemproses(false),
            onSuccess: () => AturForm(null),
        };

        if (form.uuid === null) {
            router.post(alamat, form.isian, opsi);
        } else {
            router.put(`${alamat}/${form.uuid}`, form.isian, opsi);
        }
    };

    const UbahStatus = (kategori: BarisKategoriKas) =>
        router.put(`${alamat}/${kategori.Uuid}/status`, { Aktif: !kategori.Aktif }, { preserveScroll: true });

    return (
        <TataLetakAplikasi judul="Kategori kas">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Kasir memilih kategori ini saat mencatat kas keluar (misalnya beli es batu, bayar parkir) atau kas masuk
                di luar penjualan. Setiap kategori dijurnal ke akun yang dipilih. Kategori yang sudah dipakai cukup
                dinonaktifkan, tidak dihapus.
            </p>
            <div>
                <Button onClick={() => Buka(null)}>Tambah kategori kas</Button>
            </div>

            <TabelData
                id="kasir-kategori-kas"
                label="Daftar kategori kas"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Kategori }}
                ambilIdBaris={(k) => k.Uuid}
                urutBawaan="Nama"
                cari="Cari nama kategori"
                saring={[
                    {
                        id: 'Jenis',
                        label: 'Jenis',
                        jenis: 'pilihan',
                        opsi: [
                            { nilai: 'Keluar', label: 'Kas keluar' },
                            { nilai: 'Masuk', label: 'Kas masuk' },
                        ],
                    },
                    { id: 'Aktif', label: 'Hanya yang aktif', jenis: 'ya' },
                ]}
                labelBaris={(k) => `untuk kategori ${k.Nama}`}
                aksiBaris={(k) => (
                    <ItemAksiBaris
                        aksi={[
                            { label: 'Ubah kategori', saatPilih: () => Buka(k) },
                            {
                                label: k.Aktif ? 'Nonaktifkan kategori' : 'Aktifkan kategori',
                                saatPilih: () => UbahStatus(k),
                                bahaya: k.Aktif,
                            },
                        ]}
                    />
                )}
                kosong={{
                    ilustrasi: 'Shift',
                    judul: 'Belum ada kategori kas. Tambahkan minimal satu kategori kas keluar agar kasir bisa mencatat pengeluaran dari laci.',
                }}
            />

            {form !== null ? (
                <DialogFormulir
                    judul={form.uuid === null ? 'Tambah kategori kas' : 'Ubah kategori kas'}
                    galatUmum={galat.Umum}
                    saatTutup={() => AturForm(null)}
                >
                    <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir kategori kas">
                        <BidangTeks
                            label="Nama kategori"
                            nilai={form.isian.Nama}
                            saatBerubah={(nilai) => AturForm({ ...form, isian: { ...form.isian, Nama: nilai } })}
                            galat={galat.Nama}
                            maxLength={100}
                        />
                        {form.uuid === null ? (
                            <BidangPilihan
                                label="Jenis"
                                nilai={form.isian.Jenis}
                                opsi={opsiJenis}
                                saatBerubah={(nilai) =>
                                    AturForm({
                                        ...form,
                                        isian: { ...form.isian, Jenis: nilai as JenisKategoriKas, UuidAkun: '' },
                                    })
                                }
                                galat={galat.Jenis}
                            />
                        ) : null}
                        <BidangPilihan
                            label="Akun jurnal"
                            nilai={form.isian.UuidAkun}
                            kosong="Pilih akun"
                            opsi={OpsiAkun[form.isian.Jenis].map((a) => ({
                                Nilai: a.Uuid,
                                Label: `${a.Kode} ${a.Nama}`,
                            }))}
                            saatBerubah={(nilai) => AturForm({ ...form, isian: { ...form.isian, UuidAkun: nilai } })}
                            galat={galat.UuidAkun}
                        />
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => AturForm(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={memproses}>
                                Simpan kategori
                            </Button>
                        </div>
                    </form>
                </DialogFormulir>
            ) : null}
        </TataLetakAplikasi>
    );
}

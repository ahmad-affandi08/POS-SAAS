import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import MenuAksiBaris from '@/Komponen/Tindakan/MenuAksiBaris';
import { Badge } from '@/Komponen/Ui/badge';
import { Button } from '@/Komponen/Ui/button';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisKategoriKas, JenisKategoriKas, PropsKategoriKas } from '@/Tipe/Kasir';

const alamat = '/kelola/kasir/kategori-kas';
const kelasKepala = 'h-auto px-4 py-2 text-label font-semibold text-teks-sekunder';

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

            {Kategori.length === 0 ? (
                <KeadaanKosong judul="Belum ada kategori kas. Tambahkan minimal satu kategori kas keluar agar kasir bisa mencatat pengeluaran dari laci." />
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className="min-w-[720px] text-left text-isi">
                        <TableCaption className="sr-only">Daftar kategori kas, {Kategori.length} kategori</TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Nama
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Jenis
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Akun jurnal
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Status
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Kategori.map((k) => (
                                <TableRow key={k.Uuid} className="border-garis">
                                    <TableCell className="px-4 font-semibold text-teks-utama">{k.Nama}</TableCell>
                                    <TableCell className="px-4">{k.LabelJenis}</TableCell>
                                    <TableCell className="px-4 font-mono text-label">{k.Akun ?? '—'}</TableCell>
                                    <TableCell className="px-4">
                                        <Badge variant={k.Aktif ? 'default' : 'outline'}>
                                            {k.Aktif ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="px-4 text-right">
                                        <MenuAksiBaris
                                            label={`Aksi untuk kategori ${k.Nama}`}
                                            aksi={[
                                                { label: 'Ubah kategori', saatPilih: () => Buka(k) },
                                                {
                                                    label: k.Aktif ? 'Nonaktifkan kategori' : 'Aktifkan kategori',
                                                    saatPilih: () => UbahStatus(k),
                                                    bahaya: k.Aktif,
                                                },
                                            ]}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}

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

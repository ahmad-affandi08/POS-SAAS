import { Link, router, usePage } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import PenandaJurnal from '@/Komponen/Akuntansi/PenandaJurnal';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Input } from '@/Komponen/Ui/input';
import { Label } from '@/Komponen/Ui/label';
import { Skeleton } from '@/Komponen/Ui/skeleton';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDaftarJurnal } from '@/Tipe/Akuntansi';

type SaringJurnal = PropsDaftarJurnal['Saring'];

const alamat = '/kelola/akuntansi/jurnal';
const kelasKepala = 'h-auto px-4 py-2 text-label font-semibold text-teks-sekunder';

/** Parameter query daftar jurnal (DesainF05a E); isian kosong tidak dikirim. */
export function BuatQueryJurnal(saring: SaringJurnal): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.Kata.trim() !== '') {
        query.kata = saring.Kata.trim();
    }

    if (saring.Dari !== '') {
        query.dari = saring.Dari;
    }

    if (saring.Sampai !== '') {
        query.sampai = saring.Sampai;
    }

    if (saring.JenisSumber) {
        query.jenis = saring.JenisSumber;
    }

    return query;
}

function BidangTanggal({
    label,
    nilai,
    saatBerubah,
}: {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
}) {
    const id = useId();

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </Label>
            <Input
                id={id}
                type="date"
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                className="h-10 border-garis-input bg-permukaan text-isi text-teks-utama"
            />
        </div>
    );
}

/** F-05a: daftar jurnal (baca saja). Jurnal otomatis dari dokumen sumber; koreksi lewat jurnal pembalik (aturan #8). */
export default function HalamanDaftarJurnal({ Jurnal, Saring, OpsiJenisSumber }: PropsDaftarJurnal) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [saring, AturSaring] = useState<SaringJurnal>(Saring);
    const [memuat, AturMemuat] = useState(false);
    const adaSaringan = Object.keys(BuatQueryJurnal(Saring)).length > 0;

    const Terapkan = (baru: SaringJurnal) =>
        router.get(alamat, BuatQueryJurnal(baru), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        Terapkan(saring);
    };

    const Ubah = <K extends keyof SaringJurnal>(kunci: K, nilai: SaringJurnal[K]) => {
        const baru = { ...saring, [kunci]: nilai };
        AturSaring(baru);

        if (kunci === 'JenisSumber') {
            Terapkan(baru);
        }
    };

    return (
        <TataLetakAplikasi judul="Jurnal">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Jurnal dibuat otomatis dari dokumen seperti stok awal. Jurnal yang sudah diposting tidak bisa diubah;
                koreksi dilakukan dengan membatalkan dokumen sumbernya sehingga terbentuk jurnal pembalik.
            </p>
            <DaftarGalatServer galat={props.errors} />

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Kirim}
                    role="search"
                    aria-label="Cari dan saring jurnal"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div className="sm:col-span-2">
                        <BidangTeks
                            label="Cari jurnal"
                            nilai={saring.Kata}
                            saatBerubah={(nilai) => Ubah('Kata', nilai)}
                            keterangan="Nomor jurnal, nomor dokumen sumber, atau keterangan."
                            maxLength={100}
                        />
                    </div>
                    <BidangTanggal
                        label="Dari tanggal"
                        nilai={saring.Dari}
                        saatBerubah={(nilai) => Ubah('Dari', nilai)}
                    />
                    <BidangTanggal
                        label="Sampai tanggal"
                        nilai={saring.Sampai}
                        saatBerubah={(nilai) => Ubah('Sampai', nilai)}
                    />
                    <BidangPilihan
                        label="Sumber"
                        nilai={saring.JenisSumber ?? ''}
                        kosong="Semua sumber"
                        opsi={OpsiJenisSumber}
                        saatBerubah={(nilai) => Ubah('JenisSumber', nilai === '' ? null : nilai)}
                    />
                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
                        <Button type="submit" variant="outline" className="h-10">
                            Terapkan saringan
                        </Button>
                        {adaSaringan ? (
                            <Button asChild variant="link" className="h-10 px-0">
                                <Link href={alamat}>Hapus saringan</Link>
                            </Button>
                        ) : null}
                    </div>
                </form>
            </Card>

            <div aria-live="polite" className="sr-only">
                {memuat ? 'Memuat jurnal…' : `${String(Jurnal.Total)} jurnal ditemukan.`}
            </div>

            {memuat ? (
                <div
                    aria-hidden="true"
                    data-testid="kerangka-jurnal"
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-card p-4"
                >
                    {[0, 1, 2, 3, 4].map((baris) => (
                        <Skeleton key={baris} className="h-8 rounded-kontrol" />
                    ))}
                </div>
            ) : Jurnal.Data.length === 0 ? (
                adaSaringan ? (
                    <KeadaanKosong judul="Tidak ada jurnal yang cocok dengan pencarian atau saringan ini.">
                        <Button asChild variant="link" className="h-auto px-0">
                            <Link href={alamat}>Hapus saringan</Link>
                        </Button>
                    </KeadaanKosong>
                ) : (
                    <KeadaanKosong judul="Belum ada jurnal. Jurnal terbentuk otomatis saat stok awal diposting.">
                        <Button asChild variant="outline">
                            <Link href="/kelola/persediaan/stok-awal">Buka stok awal</Link>
                        </Button>
                    </KeadaanKosong>
                )
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className="min-w-[860px] text-left text-isi">
                        <TableCaption className="sr-only">
                            Daftar jurnal, {Jurnal.Total} jurnal, terbaru di atas
                        </TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Tanggal
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Nomor
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Sumber
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Keterangan
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Nilai
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Jurnal.Data.map((jurnal) => (
                                <TableRow key={jurnal.Uuid} className="border-garis align-top">
                                    <TableCell className="px-4 whitespace-nowrap text-teks-sekunder">
                                        {FormatTanggal(jurnal.Tanggal)}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-nowrap">
                                        <Link
                                            href={`${alamat}/${jurnal.Uuid}`}
                                            className="font-mono font-semibold text-brand underline"
                                        >
                                            {jurnal.Nomor}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span className="block text-teks-utama">{jurnal.LabelJenisSumber}</span>
                                        {jurnal.NomorSumber ? (
                                            jurnal.TautanSumber ? (
                                                <Link
                                                    href={jurnal.TautanSumber}
                                                    className="font-mono text-label break-all text-brand underline"
                                                >
                                                    {jurnal.NomorSumber}
                                                </Link>
                                            ) : (
                                                <span className="font-mono text-label break-all text-teks-sekunder">
                                                    {jurnal.NomorSumber}
                                                </span>
                                            )
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span className="block break-words text-teks-utama">{jurnal.Keterangan}</span>
                                        <PenandaJurnal jurnal={jurnal} />
                                    </TableCell>
                                    <TableCell className="px-4 text-right whitespace-nowrap tabular-nums">
                                        {FormatRupiah(jurnal.TotalDebit)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}

            <Paginasi
                alamat={alamat}
                saring={BuatQueryJurnal(Saring)}
                halamanSaatIni={Jurnal.HalamanSaatIni}
                halamanTerakhir={Jurnal.HalamanTerakhir}
                total={Jurnal.Total}
                label="Halaman daftar jurnal"
            />
        </TataLetakAplikasi>
    );
}

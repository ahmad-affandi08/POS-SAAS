import { Link, router } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import LencanaShift from '@/Komponen/Kasir/LencanaShift';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
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
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsDaftarShift, SaringShift } from '@/Tipe/Kasir';

const alamat = '/kelola/kasir/shift';
const kelasKepala = 'h-auto px-4 py-2 text-label font-semibold text-teks-sekunder';

/** Parameter query daftar shift; isian kosong tidak dikirim. */
export function BuatQueryShift(saring: SaringShift): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.UuidOutlet) {
        query.outlet = saring.UuidOutlet;
    }

    if (saring.Status) {
        query.status = saring.Status;
    }

    if (saring.Dari !== '') {
        query.dari = saring.Dari;
    }

    if (saring.Sampai !== '') {
        query.sampai = saring.Sampai;
    }

    if (saring.PerluTinjauan) {
        query.tinjauan = '1';
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

/** F-06: daftar shift kasir (baca saja). Shift dibuka & diisi dari aplikasi kasir; tutup shift menyusul (F-11). */
export default function HalamanDaftarShift({ Shift, Saring, OpsiOutlet, OpsiStatus }: PropsDaftarShift) {
    const [saring, AturSaring] = useState<SaringShift>(Saring);
    const [memuat, AturMemuat] = useState(false);
    const adaSaringan = Object.keys(BuatQueryShift(Saring)).length > 0;

    const Terapkan = (baru: SaringShift) =>
        router.get(alamat, BuatQueryShift(baru), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        Terapkan(saring);
    };

    return (
        <TataLetakAplikasi judul="Shift kasir">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Shift dibuka kasir di aplikasi POS, termasuk saat offline, lalu terkirim ke sini begitu perangkat
                online. Kas masuk, kas keluar, dan setoran tercatat per shift beserta jurnalnya.
            </p>

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Kirim}
                    role="search"
                    aria-label="Saring shift"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <BidangPilihan
                        label="Outlet"
                        nilai={saring.UuidOutlet ?? ''}
                        kosong="Semua outlet"
                        opsi={OpsiOutlet.map((o) => ({ Nilai: o.Uuid, Label: o.Nama }))}
                        saatBerubah={(nilai) => AturSaring({ ...saring, UuidOutlet: nilai === '' ? null : nilai })}
                    />
                    <BidangPilihan
                        label="Status"
                        nilai={saring.Status ?? ''}
                        kosong="Semua status"
                        opsi={OpsiStatus}
                        saatBerubah={(nilai) =>
                            AturSaring({ ...saring, Status: nilai === '' ? null : (nilai as SaringShift['Status']) })
                        }
                    />
                    <BidangTanggal
                        label="Dari tanggal"
                        nilai={saring.Dari}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Dari: nilai })}
                    />
                    <BidangTanggal
                        label="Sampai tanggal"
                        nilai={saring.Sampai}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Sampai: nilai })}
                    />
                    <div className="sm:col-span-2 lg:col-span-4">
                        <KotakCentang
                            label="Hanya shift yang perlu ditinjau"
                            nilai={saring.PerluTinjauan}
                            saatBerubah={(nilai) => AturSaring({ ...saring, PerluTinjauan: nilai })}
                        />
                    </div>
                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
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
                {memuat ? 'Memuat shift…' : `${String(Shift.Total)} shift ditemukan.`}
            </div>

            {memuat ? (
                <div
                    aria-hidden="true"
                    data-testid="kerangka-shift"
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-card p-4"
                >
                    {[0, 1, 2, 3, 4].map((baris) => (
                        <Skeleton key={baris} className="h-8 rounded-kontrol" />
                    ))}
                </div>
            ) : Shift.Data.length === 0 ? (
                adaSaringan ? (
                    <KeadaanKosong judul="Tidak ada shift yang cocok dengan saringan ini.">
                        <Button asChild variant="link" className="h-auto px-0">
                            <Link href={alamat}>Hapus saringan</Link>
                        </Button>
                    </KeadaanKosong>
                ) : (
                    <KeadaanKosong judul="Belum ada shift. Shift muncul di sini setelah kasir membuka shift di aplikasi POS dan perangkatnya tersinkron." />
                )
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className="min-w-[960px] text-left text-isi">
                        <TableCaption className="sr-only">
                            Daftar shift, {Shift.Total} shift, terbaru di atas
                        </TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Dibuka
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Kasir & perangkat
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Status
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Kas awal
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Masuk
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Keluar & setoran
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Shift.Data.map((shift) => (
                                <TableRow key={shift.Uuid} className="border-garis align-top">
                                    <TableCell className="px-4 whitespace-nowrap">
                                        <Link
                                            href={`${alamat}/${shift.Uuid}`}
                                            className="font-semibold text-brand underline"
                                        >
                                            {FormatTanggalWaktu(shift.DibukaPada)}
                                        </Link>
                                        <span className="block text-label text-teks-sekunder">
                                            {shift.NamaOutlet} · hari bisnis {FormatTanggal(shift.TanggalBisnis)}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span className="block text-teks-utama">{shift.NamaKasir}</span>
                                        <span className="block font-mono text-label text-teks-sekunder">
                                            {shift.Perangkat}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4">
                                        <LencanaShift
                                            status={shift.Status}
                                            label={shift.LabelStatus}
                                            perluTinjauan={shift.PerluTinjauan}
                                            bersama={shift.Bersama}
                                        />
                                    </TableCell>
                                    <TableCell className="px-4 text-right whitespace-nowrap tabular-nums">
                                        {FormatRupiah(shift.KasAwal)}
                                    </TableCell>
                                    <TableCell className="px-4 text-right whitespace-nowrap tabular-nums">
                                        {FormatRupiah(shift.TotalMasuk)}
                                    </TableCell>
                                    <TableCell className="px-4 text-right whitespace-nowrap tabular-nums">
                                        {FormatRupiah(shift.TotalKeluar)}
                                        <span className="block text-label text-teks-sekunder">
                                            setoran {FormatRupiah(shift.TotalSetoran)}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}

            <Paginasi
                alamat={alamat}
                saring={BuatQueryShift(Saring)}
                halamanSaatIni={Shift.HalamanSaatIni}
                halamanTerakhir={Shift.HalamanTerakhir}
                total={Shift.Total}
                label="Halaman daftar shift"
            />
        </TataLetakAplikasi>
    );
}

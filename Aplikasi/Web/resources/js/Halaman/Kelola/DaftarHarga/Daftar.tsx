import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormDaftarHarga, { DaftarHargaKosong } from '@/Komponen/Katalog/FormDaftarHarga';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Komponen/Ui/alert-dialog';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Komponen/Ui/sheet';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisDaftarHarga, PropsDaftarDaftarHarga } from '@/Tipe/Katalog';

/** Ringkasan periode berlaku: "Selalu", "Mulai …", "Sampai …", atau "… – …". */
export function RingkasPeriode(baris: Pick<BarisDaftarHarga, 'MulaiPada' | 'SelesaiPada'>): string {
    if (baris.MulaiPada === null && baris.SelesaiPada === null) {
        return 'Selalu';
    }

    if (baris.SelesaiPada === null) {
        return `Mulai ${FormatTanggalWaktu(baris.MulaiPada)}`;
    }

    if (baris.MulaiPada === null) {
        return `Sampai ${FormatTanggalWaktu(baris.SelesaiPada)}`;
    }

    return `${FormatTanggalWaktu(baris.MulaiPada)} – ${FormatTanggalWaktu(baris.SelesaiPada)}`;
}

function UbahStatusDaftar(daftar: BarisDaftarHarga) {
    router.post(
        `/kelola/daftar-harga/${daftar.Uuid}/${daftar.Aktif ? 'nonaktifkan' : 'aktifkan'}`,
        {},
        { preserveScroll: true },
    );
}

/** Aktifkan langsung; nonaktifkan lewat konfirmasi karena harga di daftar ini berhenti dipakai kasir. */
function TombolStatusDaftar({ daftar }: { daftar: BarisDaftarHarga }) {
    if (!daftar.Aktif) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => UbahStatusDaftar(daftar)}
                aria-label={`Aktifkan ${daftar.Nama}`}
            >
                Aktifkan
            </Button>
        );
    }

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button type="button" variant="outline" size="sm" aria-label={`Nonaktifkan ${daftar.Nama}`}>
                    Nonaktifkan
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Nonaktifkan {daftar.Nama}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Kasir berhenti memakai harga di daftar ini dan kembali ke harga dasar atau daftar lain yang
                        cocok. Daftar bisa diaktifkan lagi kapan saja.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" onClick={() => UbahStatusDaftar(daftar)}>
                        Nonaktifkan daftar
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** F-03 daftar harga per outlet, kanal, tingkat pelanggan, dan periode. Tidak pernah dihapus, hanya dinonaktifkan. */
export default function HalamanDaftarDaftarHarga({
    DaftarHarga,
    Outlet,
    Kanal,
    ZonaWaktu,
    Izin,
}: PropsDaftarDaftarHarga) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [formTerbuka, AturFormTerbuka] = useState(false);

    return (
        <TataLetakAplikasi judul="Daftar harga">
            {!Izin.UbahHarga ? <PesanHanyaLihat izin="produk.harga.ubah" objek="daftar harga" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={formTerbuka ? Object.keys(props.errors) : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="max-w-2xl text-isi text-teks-sekunder">
                    Harga khusus untuk outlet, kanal (misal online), tingkat pelanggan, atau periode tertentu. Bila
                    beberapa daftar cocok, prioritas terbesar dipakai; bila sama, yang syaratnya lebih spesifik. Produk
                    tanpa harga di daftar memakai harga dasar.
                </p>
                {Izin.UbahHarga ? (
                    <Button type="button" onClick={() => AturFormTerbuka(true)}>
                        Buat daftar harga
                    </Button>
                ) : null}
            </div>
            <Sheet open={formTerbuka} onOpenChange={AturFormTerbuka}>
                {formTerbuka ? (
                    <SheetContent showCloseButton={false} className="w-full overflow-y-auto sm:max-w-xl">
                        <SheetHeader>
                            <SheetTitle>Buat daftar harga</SheetTitle>
                            <SheetDescription>
                                Atur untuk outlet, kanal, tingkat pelanggan, dan periode mana daftar ini berlaku.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="px-4 pb-4">
                            <FormDaftarHarga
                                uuid={null}
                                awal={DaftarHargaKosong}
                                outlet={Outlet}
                                kanal={Kanal}
                                zonaWaktu={ZonaWaktu}
                                saatSelesai={() => AturFormTerbuka(false)}
                            />
                        </div>
                    </SheetContent>
                ) : null}
            </Sheet>

            {DaftarHarga.Data.length === 0 ? (
                <KeadaanKosong judul="Belum ada daftar harga. Semua produk memakai harga dasar." />
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[860px] text-isi">
                        <TableCaption className="sr-only">Daftar harga, {DaftarHarga.Total} daftar</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col" className="px-4">
                                    Nama
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Berlaku untuk
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Periode
                                </TableHead>
                                <TableHead scope="col" className="px-4 text-right">
                                    Prioritas
                                </TableHead>
                                <TableHead scope="col" className="px-4 text-right">
                                    Produk
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Status
                                </TableHead>
                                {Izin.UbahHarga ? (
                                    <TableHead scope="col" className="px-4">
                                        <span className="sr-only">Aksi</span>
                                    </TableHead>
                                ) : null}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {DaftarHarga.Data.map((daftar) => (
                                <TableRow key={daftar.Uuid} className="align-top">
                                    <TableCell className="px-4 whitespace-normal">
                                        <Link
                                            href={`/kelola/daftar-harga/${daftar.Uuid}`}
                                            className="font-semibold break-words text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            {daftar.Nama}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal text-teks-sekunder">
                                        {[
                                            daftar.NamaOutlet === null ? 'Semua outlet' : daftar.NamaOutlet.join(', '),
                                            daftar.LabelKanal ?? 'Semua kanal',
                                            daftar.TierPelanggan ? `Pelanggan ${daftar.TierPelanggan}` : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal text-teks-sekunder">
                                        {RingkasPeriode(daftar)}
                                    </TableCell>
                                    <TableCell className="px-4 text-right tabular-nums">{daftar.Prioritas}</TableCell>
                                    <TableCell className="px-4 text-right tabular-nums">
                                        {daftar.JumlahProduk}
                                    </TableCell>
                                    <TableCell className="px-4">
                                        <LabelStatus
                                            jenis={daftar.Aktif ? 'sukses' : 'netral'}
                                            teks={daftar.Aktif ? 'Aktif' : 'Nonaktif'}
                                        />
                                    </TableCell>
                                    {Izin.UbahHarga ? (
                                        <TableCell className="px-4 text-right">
                                            <TombolStatusDaftar daftar={daftar} />
                                        </TableCell>
                                    ) : null}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
            <Paginasi
                alamat="/kelola/daftar-harga"
                saring={{}}
                halamanSaatIni={DaftarHarga.HalamanSaatIni}
                halamanTerakhir={DaftarHarga.HalamanTerakhir}
                total={DaftarHarga.Total}
                label="Halaman daftar harga"
            />
        </TataLetakAplikasi>
    );
}

import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { AmbilGalatBerawalan } from '@/Komponen/Katalog/BantuanKatalog';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormDaftarHarga from '@/Komponen/Katalog/FormDaftarHarga';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import SakelarPadat, { KelasSel, usePadatTabel } from '@/Komponen/Katalog/SakelarPadat';
import TabelHargaBertingkat, { PeriksaBarisHarga } from '@/Komponen/Katalog/TabelHargaBertingkat';
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
import { Skeleton } from '@/Komponen/Ui/skeleton';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisHarga, PropsDetailDaftarHarga } from '@/Tipe/Katalog';

type BarisProdukDaftar = PropsDetailDaftarHarga['Baris']['Data'][number];

/** Hanya baris yang berubah yang dikirim; server mengganti set harga per satuan produk (DesainF03 C.3). */
export function AmbilBarisBerubah(
    awal: BarisProdukDaftar[],
    harga: Record<string, BarisHarga[]>,
): { UuidProdukSatuan: string; Harga: BarisHarga[] }[] {
    return awal
        .filter((baris) => JSON.stringify(harga[baris.UuidProdukSatuan] ?? []) !== JSON.stringify(baris.Harga))
        .map((baris) => ({ UuidProdukSatuan: baris.UuidProdukSatuan, Harga: harga[baris.UuidProdukSatuan] ?? [] }));
}

/** F-03 isi satu daftar harga: pengaturan, status, dan harga per produk-satuan (boleh bertingkat, misal 12+). */
export default function HalamanDetailDaftarHarga({
    DaftarHarga,
    Baris,
    Saring,
    Outlet,
    Kanal,
    ZonaWaktu,
    Izin,
}: PropsDetailDaftarHarga) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [ubahPengaturan, AturUbahPengaturan] = useState(false);
    const [kata, AturKata] = useState(Saring.Kata);
    const [memuat, AturMemuat] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const [periksa, AturPeriksa] = useState(false);
    const [padat, AturPadat] = usePadatTabel('DaftarHarga');
    const [harga, AturHarga] = useState<Record<string, BarisHarga[]>>(() =>
        Object.fromEntries(Baris.Data.map((baris) => [baris.UuidProdukSatuan, baris.Harga])),
    );
    const berubah = AmbilBarisBerubah(Baris.Data, harga);
    const sel = KelasSel(padat);
    const alamat = `/kelola/daftar-harga/${DaftarHarga.Uuid}`;
    const formAwal = {
        Nama: DaftarHarga.Nama,
        UuidOutlet: DaftarHarga.UuidOutlet,
        Kanal: DaftarHarga.Kanal,
        TierPelanggan: DaftarHarga.TierPelanggan,
        MulaiPada: DaftarHarga.MulaiPada,
        SelesaiPada: DaftarHarga.SelesaiPada,
        Prioritas: DaftarHarga.Prioritas,
    };

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get(alamat, kata.trim() === '' ? {} : { kata: kata.trim() }, {
            preserveState: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });
    };

    const UbahStatus = () =>
        router.post(`${alamat}/${DaftarHarga.Aktif ? 'nonaktifkan' : 'aktifkan'}`, {}, { preserveScroll: true });

    const Simpan = () => {
        AturPeriksa(true);
        const salah = Baris.Data.some((baris) => {
            const hasil = PeriksaBarisHarga(harga[baris.UuidProdukSatuan] ?? [], {
                wajibDasar: false,
                bolehDesimal: true,
            });

            return Object.keys(hasil.perBaris).length > 0;
        });

        if (salah || berubah.length === 0) {
            return;
        }

        router.put(
            `${alamat}/harga`,
            { Baris: berubah },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <TataLetakAplikasi judul={DaftarHarga.Nama}>
            <p className="text-label">
                <Link href="/kelola/daftar-harga" className="font-semibold text-brand underline">
                    Kembali ke daftar harga
                </Link>
            </p>
            {!Izin.UbahHarga ? <PesanHanyaLihat izin="produk.harga.ubah" objek="daftar harga ini" /> : null}
            <DaftarGalatServer
                galat={props.errors}
                kecuali={Object.keys(props.errors).filter((kunci) => ubahPengaturan || /^Baris\./.test(kunci))}
            />

            <div className="flex flex-wrap items-center gap-2">
                <LabelStatus
                    jenis={DaftarHarga.Aktif ? 'sukses' : 'netral'}
                    teks={DaftarHarga.Aktif ? 'Aktif' : 'Nonaktif'}
                />
                <span className="text-isi text-teks-sekunder">
                    {[
                        DaftarHarga.UuidOutlet.length === 0
                            ? 'Semua outlet'
                            : Outlet.filter((item) => DaftarHarga.UuidOutlet.includes(item.Nilai))
                                  .map((item) => item.Label)
                                  .join(', '),
                        Kanal.find((item) => item.Nilai === DaftarHarga.Kanal)?.Label ?? 'Semua kanal',
                        DaftarHarga.TierPelanggan ? `Pelanggan ${DaftarHarga.TierPelanggan}` : null,
                        `Prioritas ${DaftarHarga.Prioritas}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
                {Izin.UbahHarga ? (
                    <span className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" onClick={() => AturUbahPengaturan(true)}>
                            Ubah pengaturan
                        </Button>
                        {DaftarHarga.Aktif ? (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button type="button" variant="outline">
                                        Nonaktifkan daftar
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Nonaktifkan {DaftarHarga.Nama}?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Kasir berhenti memakai harga di daftar ini dan kembali ke harga dasar atau
                                            daftar lain yang cocok. Daftar bisa diaktifkan lagi kapan saja.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                        <AlertDialogAction variant="destructive" onClick={UbahStatus}>
                                            Nonaktifkan daftar
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        ) : (
                            <Button type="button" variant="outline" onClick={UbahStatus}>
                                Aktifkan daftar
                            </Button>
                        )}
                    </span>
                ) : null}
            </div>

            <Sheet open={ubahPengaturan} onOpenChange={AturUbahPengaturan}>
                {ubahPengaturan ? (
                    <SheetContent showCloseButton={false} className="w-full overflow-y-auto sm:max-w-xl">
                        <SheetHeader>
                            <SheetTitle>Ubah pengaturan {DaftarHarga.Nama}</SheetTitle>
                            <SheetDescription>
                                Atur untuk outlet, kanal, tingkat pelanggan, dan periode mana daftar ini berlaku.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="px-4 pb-4">
                            <FormDaftarHarga
                                uuid={DaftarHarga.Uuid}
                                awal={formAwal}
                                outlet={Outlet}
                                kanal={Kanal}
                                zonaWaktu={ZonaWaktu}
                                saatSelesai={() => AturUbahPengaturan(false)}
                            />
                        </div>
                    </SheetContent>
                ) : null}
            </Sheet>

            <form
                onSubmit={Cari}
                role="search"
                aria-label="Cari produk di daftar harga"
                className="flex flex-wrap items-end gap-2"
            >
                <div className="w-full max-w-sm">
                    <BidangTeks label="Cari produk" nilai={kata} saatBerubah={AturKata} maxLength={100} />
                </div>
                <Button type="submit" variant="outline">
                    Cari
                </Button>
                <SakelarPadat padat={padat} saatBerubah={AturPadat} />
            </form>

            <div aria-live="polite" className="sr-only">
                {memuat ? 'Memuat produk…' : `${String(Baris.Total)} produk-satuan.`}
            </div>

            {memuat ? (
                <Card aria-hidden="true" className="gap-2 p-4">
                    {[0, 1, 2, 3].map((baris) => (
                        <Skeleton key={baris} className="h-8" />
                    ))}
                </Card>
            ) : Baris.Data.length === 0 ? (
                <KeadaanKosong
                    judul={
                        Saring.Kata
                            ? `Tidak ada produk yang cocok dengan "${Saring.Kata}".`
                            : 'Belum ada produk yang bisa dijual.'
                    }
                >
                    {Saring.Kata ? (
                        <Link href={alamat} className="font-semibold text-brand underline">
                            Hapus pencarian
                        </Link>
                    ) : null}
                </KeadaanKosong>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className={`min-w-[760px] ${padat ? 'text-label' : 'text-isi'}`}>
                        <TableCaption className="sr-only">Harga produk di {DaftarHarga.Nama}</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col" className={sel}>
                                    Produk
                                </TableHead>
                                <TableHead scope="col" className={`${sel} text-right`}>
                                    Harga dasar
                                </TableHead>
                                <TableHead scope="col" className={sel}>
                                    Harga di daftar ini
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Baris.Data.map((baris, indeks) => (
                                <TableRow key={baris.UuidProdukSatuan} className="align-top">
                                    <TableHead scope="row" className={`${sel} h-auto font-normal whitespace-normal`}>
                                        <span className="block font-semibold break-words text-teks-utama">
                                            {baris.NamaProduk}
                                        </span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            <span className="font-mono">{baris.Sku ?? 'Tanpa SKU'}</span> · per{' '}
                                            {baris.NamaSatuan}
                                        </span>
                                    </TableHead>
                                    <TableCell className={`${sel} text-right tabular-nums text-teks-sekunder`}>
                                        {baris.HargaDasar === null ? '—' : FormatRupiah(baris.HargaDasar)}
                                    </TableCell>
                                    <TableCell className={`${sel} whitespace-normal`}>
                                        <TabelHargaBertingkat
                                            judul={`Harga ${baris.NamaProduk} per ${baris.NamaSatuan}`}
                                            baris={harga[baris.UuidProdukSatuan] ?? []}
                                            saatBerubah={(nilai) =>
                                                AturHarga({ ...harga, [baris.UuidProdukSatuan]: nilai })
                                            }
                                            simbolSatuan={baris.NamaSatuan}
                                            bolehDesimal
                                            wajibDasar={false}
                                            galatServer={AmbilGalatBerawalan(
                                                props.errors,
                                                `Baris.${String(indeks)}.Harga`,
                                            )}
                                            tampilkanGalat={periksa}
                                            disabled={!Izin.UbahHarga}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
            <Paginasi
                alamat={alamat}
                saring={Saring.Kata ? { kata: Saring.Kata } : {}}
                halamanSaatIni={Baris.HalamanSaatIni}
                halamanTerakhir={Baris.HalamanTerakhir}
                total={Baris.Total}
                label="Halaman produk daftar harga"
            />
            {Izin.UbahHarga && Baris.Data.length > 0 ? (
                <div className="sticky bottom-0 flex flex-wrap items-center gap-3 border-t border-garis bg-latar py-3">
                    <Tombol onClick={Simpan} memproses={memproses} disabled={berubah.length === 0}>
                        Simpan harga
                    </Tombol>
                    <span aria-live="polite" className="text-label text-teks-sekunder tabular-nums">
                        {berubah.length === 0
                            ? 'Belum ada perubahan di halaman ini.'
                            : `${String(berubah.length)} produk berubah.`}
                    </span>
                </div>
            ) : null}
        </TataLetakAplikasi>
    );
}

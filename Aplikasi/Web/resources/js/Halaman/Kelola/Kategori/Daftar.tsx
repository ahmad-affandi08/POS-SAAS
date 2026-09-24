import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Komponen/Ui/dialog';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDaftarKategori } from '@/Tipe/Katalog';

type Kategori = PropsDaftarKategori['Kategori'][number];

/** Kedalaman maksimal kategori (config katalog.Kategori.MaksimalKedalaman). */
export const MaksimalKedalamanKategori = 3;

/** Uuid kategori beserta seluruh turunannya (tidak boleh dipilih sebagai induk barunya sendiri). */
export function AmbilTurunanKategori(kategori: Kategori[], uuid: string): Set<string> {
    const hasil = new Set([uuid]);
    let bertambah = true;

    while (bertambah) {
        bertambah = false;

        for (const item of kategori) {
            if (item.UuidInduk !== null && hasil.has(item.UuidInduk) && !hasil.has(item.Uuid)) {
                hasil.add(item.Uuid);
                bertambah = true;
            }
        }
    }

    return hasil;
}

/** Indentasi baris menurut tingkat kategori (1–3). */
function KelasIndentasi(kedalaman: number): string {
    return kedalaman >= 3 ? 'pl-10' : kedalaman === 2 ? 'pl-5' : '';
}

function FormKategori({
    kategori,
    semua,
    saatSelesai,
}: {
    kategori: Kategori | null;
    semua: Kategori[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        Nama: kategori?.Nama ?? '',
        UuidInduk: kategori?.UuidInduk ?? null,
        Urutan: kategori ? String(kategori.Urutan) : '0',
    });
    const galat = formulir.errors as Record<string, string | undefined>;
    const terlarang = kategori ? AmbilTurunanKategori(semua, kategori.Uuid) : new Set<string>();
    const opsiInduk = semua.filter((item) => item.Kedalaman < MaksimalKedalamanKategori && !terlarang.has(item.Uuid));

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (kategori === null) {
            formulir.post('/kelola/kategori', opsi);
        } else {
            formulir.put(`/kelola/kategori/${kategori.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-label={kategori ? `Ubah kategori ${kategori.Nama}` : 'Tambah kategori'}
            className="flex flex-col gap-4"
        >
            <BidangTeks
                label="Nama kategori"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={galat.Nama}
                maxLength={100}
                autoFocus
                required
            />
            <BidangPilihan
                label="Induk kategori"
                nilai={formulir.data.UuidInduk ?? ''}
                kosong="Tanpa induk (tingkat teratas)"
                opsi={opsiInduk.map((item) => ({ Nilai: item.Uuid, Label: item.Jalur }))}
                saatBerubah={(nilai) => formulir.setData('UuidInduk', nilai === '' ? null : nilai)}
                galat={galat.UuidInduk}
            />
            <BidangTeks
                label="Urutan tampil"
                nilai={formulir.data.Urutan}
                saatBerubah={(nilai) => formulir.setData('Urutan', nilai.replace(/\D/g, ''))}
                galat={galat.Urutan}
                inputMode="numeric"
                maxLength={4}
                keterangan="Angka kecil tampil lebih dulu di kasir."
            />
            <DialogFooter>
                <Button type="button" variant="outline" onClick={saatSelesai}>
                    Batal
                </Button>
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kategori
                </Tombol>
            </DialogFooter>
        </form>
    );
}

/** Tombol hapus + konfirmasi. Hanya tampil untuk kategori tanpa sub-kategori dan tanpa produk. */
function TombolHapusKategori({ kategori }: { kategori: Kategori }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    aria-label={`Hapus kategori ${kategori.Nama}`}
                >
                    Hapus
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus kategori {kategori.Nama}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Kategori ini tidak punya sub-kategori dan tidak dipakai produk mana pun.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        onClick={() => router.delete(`/kelola/kategori/${kategori.Uuid}`, { preserveScroll: true })}
                    >
                        Hapus kategori
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** F-03 kategori bertingkat (maks 3 tingkat). Hapus hanya bila tanpa sub-kategori dan tanpa produk. */
export default function HalamanDaftarKategori({ Kategori, Izin }: PropsDaftarKategori) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<Kategori | 'baru' | null>(null);
    const CekPunyaAnak = (uuid: string) => Kategori.some((item) => item.UuidInduk === uuid);

    return (
        <TataLetakAplikasi judul="Kategori produk">
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="kategori" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={sunting !== null ? ['Nama', 'UuidInduk', 'Urutan'] : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-isi text-teks-sekunder">
                    Kelompokkan produk sampai 3 tingkat, misal Minuman › Kopi › Kopi susu.
                </p>
                {Izin.Kelola ? (
                    <Button type="button" onClick={() => AturSunting('baru')}>
                        Tambah kategori
                    </Button>
                ) : null}
            </div>
            <Dialog open={sunting !== null} onOpenChange={(buka) => (buka ? undefined : AturSunting(null))}>
                {sunting !== null ? (
                    <DialogContent showCloseButton={false}>
                        <DialogHeader>
                            <DialogTitle>
                                {sunting === 'baru' ? 'Tambah kategori' : `Ubah kategori ${sunting.Nama}`}
                            </DialogTitle>
                            <DialogDescription>
                                Kategori bisa bertingkat sampai 3 tingkat, misal Minuman › Kopi › Kopi susu.
                            </DialogDescription>
                        </DialogHeader>
                        <FormKategori
                            key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                            kategori={sunting === 'baru' ? null : sunting}
                            semua={Kategori}
                            saatSelesai={() => AturSunting(null)}
                        />
                    </DialogContent>
                ) : null}
            </Dialog>
            {Kategori.length === 0 ? (
                <KeadaanKosong judul="Belum ada kategori. Tambah kategori agar produk mudah dicari di kasir." />
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[560px] text-isi">
                        <TableCaption className="sr-only">Daftar kategori</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col" className="px-4">
                                    Kategori
                                </TableHead>
                                <TableHead scope="col" className="px-4 text-right">
                                    Urutan
                                </TableHead>
                                <TableHead scope="col" className="px-4 text-right">
                                    Produk
                                </TableHead>
                                {Izin.Kelola ? (
                                    <TableHead scope="col" className="px-4">
                                        <span className="sr-only">Aksi</span>
                                    </TableHead>
                                ) : null}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Kategori.map((item) => (
                                <TableRow key={item.Uuid}>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span
                                            className={`block font-semibold break-words text-teks-utama ${KelasIndentasi(item.Kedalaman)}`}
                                        >
                                            {item.Nama}
                                        </span>
                                        {item.Kedalaman > 1 ? (
                                            <span
                                                className={`block text-keterangan text-teks-sekunder ${KelasIndentasi(item.Kedalaman)}`}
                                            >
                                                {item.Jalur}
                                            </span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 text-right tabular-nums text-teks-sekunder">
                                        {item.Urutan}
                                    </TableCell>
                                    <TableCell className="px-4 text-right tabular-nums">{item.JumlahProduk}</TableCell>
                                    {Izin.Kelola ? (
                                        <TableCell className="px-4">
                                            <span className="flex justify-end gap-1">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => AturSunting(item)}
                                                    aria-label={`Ubah kategori ${item.Nama}`}
                                                >
                                                    Ubah
                                                </Button>
                                                {item.JumlahProduk === 0 && !CekPunyaAnak(item.Uuid) ? (
                                                    <TombolHapusKategori kategori={item} />
                                                ) : null}
                                            </span>
                                        </TableCell>
                                    ) : null}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
        </TataLetakAplikasi>
    );
}

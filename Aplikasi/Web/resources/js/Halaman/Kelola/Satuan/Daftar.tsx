import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
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
import { Badge } from '@/Komponen/Ui/badge';
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
import type { PropsDaftarSatuan } from '@/Tipe/Katalog';

type Satuan = PropsDaftarSatuan['Satuan'][number];

function FormSatuan({ satuan, saatSelesai }: { satuan: Satuan | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Nama: satuan?.Nama ?? '',
        Simbol: satuan?.Simbol ?? '',
        BolehDesimal: satuan?.BolehDesimal ?? false,
    });
    const galat = formulir.errors as Record<string, string | undefined>;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (satuan === null) {
            formulir.post('/kelola/satuan', opsi);
        } else {
            formulir.put(`/kelola/satuan/${satuan.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-label={satuan ? `Ubah satuan ${satuan.Nama}` : 'Tambah satuan'}
            className="flex flex-col gap-4"
        >
            <BidangTeks
                label="Nama satuan"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={galat.Nama}
                keterangan="Misal Kilogram, Porsi, atau Dus."
                maxLength={50}
                autoFocus
                required
            />
            <BidangTeks
                label="Simbol"
                nilai={formulir.data.Simbol}
                saatBerubah={(nilai) => formulir.setData('Simbol', nilai)}
                galat={galat.Simbol}
                keterangan="Tampil di struk dan tabel, misal kg."
                maxLength={10}
                required
            />
            <div className="flex flex-col gap-1">
                <KotakCentang
                    label="Boleh pecahan (misal 0,5 kg)"
                    nilai={formulir.data.BolehDesimal}
                    saatBerubah={(nilai) => formulir.setData('BolehDesimal', nilai)}
                />
                {galat.BolehDesimal ? (
                    <p className="text-keterangan font-semibold text-bahaya">{galat.BolehDesimal}</p>
                ) : (
                    <p className="text-keterangan text-teks-sekunder">
                        Tidak bisa diubah bila satuan ini sudah menjadi satuan dasar produk.
                    </p>
                )}
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={saatSelesai}>
                    Batal
                </Button>
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan satuan
                </Tombol>
            </DialogFooter>
        </form>
    );
}

/** Tombol hapus + konfirmasi. Hanya tampil untuk satuan yang belum dipakai produk. */
function TombolHapusSatuan({ satuan }: { satuan: Satuan }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    aria-label={`Hapus satuan ${satuan.Nama}`}
                >
                    Hapus
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus satuan {satuan.Nama}?</AlertDialogTitle>
                    <AlertDialogDescription>Satuan ini belum dipakai produk mana pun.</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        onClick={() => router.delete(`/kelola/satuan/${satuan.Uuid}`, { preserveScroll: true })}
                    >
                        Hapus satuan
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** F-03 satuan ukur tenant: standar (dari referensi) dan buatan sendiri. */
export default function HalamanDaftarSatuan({ Satuan, Izin }: PropsDaftarSatuan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<Satuan | 'baru' | null>(null);

    return (
        <TataLetakAplikasi judul="Satuan">
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="satuan" /> : null}
            <DaftarGalatServer
                galat={props.errors}
                kecuali={sunting !== null ? ['Nama', 'Simbol', 'BolehDesimal'] : []}
            />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-isi text-teks-sekunder">
                    Satuan dipakai untuk stok, harga, dan resep. Konversi (misal 1 dus = 24 pcs) diatur per produk.
                </p>
                {Izin.Kelola ? (
                    <Button type="button" onClick={() => AturSunting('baru')}>
                        Tambah satuan
                    </Button>
                ) : null}
            </div>
            <Dialog open={sunting !== null} onOpenChange={(buka) => (buka ? undefined : AturSunting(null))}>
                {sunting !== null ? (
                    <DialogContent showCloseButton={false}>
                        <DialogHeader>
                            <DialogTitle>
                                {sunting === 'baru' ? 'Tambah satuan' : `Ubah satuan ${sunting.Nama}`}
                            </DialogTitle>
                            <DialogDescription>
                                Satuan dipakai untuk stok, harga, dan resep produk.
                            </DialogDescription>
                        </DialogHeader>
                        <FormSatuan
                            key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                            satuan={sunting === 'baru' ? null : sunting}
                            saatSelesai={() => AturSunting(null)}
                        />
                    </DialogContent>
                ) : null}
            </Dialog>
            {Satuan.length === 0 ? (
                <KeadaanKosong judul="Belum ada satuan. Tambah satuan, misal pcs atau kg." />
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[560px] text-isi">
                        <TableCaption className="sr-only">Daftar satuan</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col" className="px-4">
                                    Satuan
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Pecahan
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Asal
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
                            {Satuan.map((item) => (
                                <TableRow key={item.Uuid}>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span className="font-semibold text-teks-utama">{item.Nama}</span>{' '}
                                        <span className="text-teks-sekunder">({item.Simbol})</span>
                                    </TableCell>
                                    <TableCell className="px-4 text-teks-sekunder">
                                        {item.BolehDesimal ? 'Boleh' : 'Tidak'}
                                    </TableCell>
                                    <TableCell className="px-4 text-teks-sekunder">
                                        {item.KodeStandar ? (
                                            <span className="inline-flex items-center gap-2">
                                                Standar
                                                <Badge variant="secondary" className="font-mono">
                                                    {item.KodeStandar}
                                                </Badge>
                                            </span>
                                        ) : (
                                            'Buatan sendiri'
                                        )}
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
                                                    aria-label={`Ubah satuan ${item.Nama}`}
                                                >
                                                    Ubah
                                                </Button>
                                                {item.JumlahProduk === 0 ? <TombolHapusSatuan satuan={item} /> : null}
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

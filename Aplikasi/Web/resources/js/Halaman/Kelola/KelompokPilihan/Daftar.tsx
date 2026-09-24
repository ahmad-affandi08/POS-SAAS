import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { JenisBahan } from '@/Komponen/Katalog/BantuanKatalog';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
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
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { FieldError, FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Komponen/Ui/sheet';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { BandingkanDesimal, CekDesimalValid, FormatJumlahSatuan } from '@/Pustaka/MasukanJumlah';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { FormKelompokPilihan, FormPilihan, PropsDaftarKelompokPilihan } from '@/Tipe/Katalog';

type Kelompok = PropsDaftarKelompokPilihan['KelompokPilihan'][number];
/** Baris pilihan di formulir + nama bahan untuk tampilan (tidak dikirim ke server). */
type BarisPilihan = FormPilihan & { NamaBahan: string | null };
type PilihanTampil = FormPilihan & { NamaProdukBahan: string | null; SimbolSatuanBahan: string | null };

/**
 * Kontrak E.9 menulis `FormKelompokPilihan & { Pilihan: (FormPilihan & {…})[] }`; interseksi dua tipe array membuat
 * TypeScript memakai elemen `FormPilihan` saat `.map`. Nilai runtime-nya memang berisi NamaProdukBahan, jadi dipersempit di sini.
 */
function AmbilPilihanTampil(kelompok: Kelompok): PilihanTampil[] {
    return kelompok.Pilihan as PilihanTampil[];
}

export const MaksimalPilihan = 50;

/** Aturan batas pilih (DesainF03 C.4 BatasPilihanTidakValid) dan nama unik; null = sesuai. */
export function PeriksaKelompokPilihan(
    data: Pick<FormKelompokPilihan, 'MinimalPilih' | 'MaksimalPilih'> & { Pilihan: FormPilihan[] },
): string | null {
    const minimal = data.MinimalPilih === '' ? 0 : Number.parseInt(data.MinimalPilih, 10);
    const maksimal = data.MaksimalPilih === '' ? 0 : Number.parseInt(data.MaksimalPilih, 10);
    const aktif = data.Pilihan.filter((item) => item.Aktif).length;
    const nama = data.Pilihan.map((item) => item.Nama.trim().toLowerCase());

    if (data.Pilihan.length === 0 || data.Pilihan.length > MaksimalPilihan) {
        return `Isi 1 sampai ${String(MaksimalPilihan)} pilihan.`;
    }

    if (maksimal < 1 || maksimal > 20) {
        return 'Maksimal pilih harus 1 sampai 20.';
    }

    if (minimal > maksimal) {
        return 'Minimal pilih tidak boleh lebih besar dari maksimal pilih.';
    }

    if (minimal > aktif) {
        return `Minimal pilih ${String(minimal)}, tetapi hanya ${String(aktif)} pilihan aktif.`;
    }

    if (nama.some((item) => item === '') || new Set(nama).size !== nama.length) {
        return 'Setiap pilihan perlu nama, dan nama tidak boleh sama dalam satu kelompok.';
    }

    return null;
}

/** Ringkasan aturan kelompok: "Wajib pilih 1", "Opsional, maks 3". */
export function RingkasAturanPilih(minimal: string, maksimal: string): string {
    return minimal !== '' && minimal !== '0'
        ? `Wajib pilih ${minimal === maksimal ? minimal : `${minimal}–${maksimal}`}`
        : `Opsional, maks ${maksimal}`;
}

function FormKelompok({
    kelompok,
    bolehUbahHarga,
    saatSelesai,
}: {
    kelompok: Kelompok | null;
    bolehUbahHarga: boolean;
    saatSelesai: () => void;
}) {
    const formulir = useForm<Omit<FormKelompokPilihan, 'Pilihan'> & { Pilihan: BarisPilihan[] }>({
        Nama: kelompok?.Nama ?? '',
        MinimalPilih: kelompok?.MinimalPilih ?? '0',
        MaksimalPilih: kelompok?.MaksimalPilih ?? '1',
        Urutan: kelompok?.Urutan ?? '0',
        Pilihan: (kelompok ? AmbilPilihanTampil(kelompok) : null)?.map((item) => ({
            Uuid: item.Uuid,
            Nama: item.Nama,
            Harga: item.Harga,
            Aktif: item.Aktif,
            UuidProdukBahan: item.UuidProdukBahan,
            Jumlah: item.Jumlah,
            NamaBahan: item.NamaProdukBahan ? `${item.NamaProdukBahan} (${item.SimbolSatuanBahan ?? ''})` : null,
        })) ?? [{ Uuid: null, Nama: '', Harga: '0', Aktif: true, UuidProdukBahan: null, Jumlah: '', NamaBahan: null }],
    });
    const data = formulir.data;
    const galat = formulir.errors as Record<string, string | undefined>;
    const [periksa, AturPeriksa] = useState(false);
    const [elemen, AturElemen] = useState<HTMLFormElement | null>(null);
    const galatLokal = periksa ? PeriksaKelompokPilihan(data) : null;
    const UbahPilihan = (indeks: number, perubahan: Partial<BarisPilihan>) =>
        formulir.setData((lama) => ({
            ...lama,
            Pilihan: lama.Pilihan.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)),
        }));

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (PeriksaKelompokPilihan(data) !== null) {
            return;
        }

        const opsi = {
            preserveScroll: true,
            onSuccess: saatSelesai,
            onError: () => FokusGalatPertama(elemen),
        };

        formulir.transform((isi) => ({
            ...isi,
            Pilihan: isi.Pilihan.map((pilihan) => ({
                Uuid: pilihan.Uuid,
                Nama: pilihan.Nama,
                Harga: pilihan.Harga,
                Aktif: pilihan.Aktif,
                UuidProdukBahan: pilihan.UuidProdukBahan,
                Jumlah: pilihan.Jumlah,
            })),
        }));

        if (kelompok === null) {
            formulir.post('/kelola/kelompok-pilihan', opsi);
        } else {
            formulir.put(`/kelola/kelompok-pilihan/${kelompok.Uuid}`, opsi);
        }
    };

    return (
        <form
            ref={AturElemen}
            onSubmit={Kirim}
            noValidate
            aria-label={kelompok ? `Ubah kelompok pilihan ${kelompok.Nama}` : 'Tambah kelompok pilihan'}
            className="flex flex-col gap-4"
        >
            <RingkasanGalatFormulir galat={{ ...galat, ...(galatLokal ? { Pilihan: galatLokal } : {}) }} />
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <BidangTeks
                        label="Nama kelompok"
                        nilai={data.Nama}
                        saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                        galat={galat.Nama}
                        keterangan="Misal Level gula, Ukuran, atau Topping."
                        maxLength={100}
                        autoFocus
                        required
                    />
                </div>
                <BidangTeks
                    label="Minimal pilih"
                    nilai={data.MinimalPilih}
                    saatBerubah={(nilai) => formulir.setData('MinimalPilih', nilai.replace(/\D/g, ''))}
                    galat={galat.MinimalPilih}
                    inputMode="numeric"
                    maxLength={2}
                    keterangan="0 = boleh dilewati kasir."
                />
                <BidangTeks
                    label="Maksimal pilih"
                    nilai={data.MaksimalPilih}
                    saatBerubah={(nilai) => formulir.setData('MaksimalPilih', nilai.replace(/\D/g, ''))}
                    galat={galat.MaksimalPilih}
                    inputMode="numeric"
                    maxLength={2}
                />
            </div>
            <FieldSet className="gap-3">
                <FieldLegend variant="label" className="mb-0 text-label font-semibold text-teks-utama">
                    Pilihan ({data.Pilihan.length})
                </FieldLegend>
                {data.Pilihan.map((pilihan, indeks) => (
                    <Card key={pilihan.Uuid ?? `baru-${String(indeks)}`} className="gap-3 p-3 shadow-none">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <BidangTeks
                                label={`Nama pilihan ${String(indeks + 1)}`}
                                nilai={pilihan.Nama}
                                saatBerubah={(nilai) => UbahPilihan(indeks, { Nama: nilai })}
                                galat={galat[`Pilihan.${String(indeks)}.Nama`]}
                                maxLength={100}
                            />
                            <BidangUang
                                label={`Tambahan harga ${String(indeks + 1)}`}
                                nilai={pilihan.Harga}
                                saatBerubah={(nilai) => UbahPilihan(indeks, { Harga: nilai })}
                                galat={galat[`Pilihan.${String(indeks)}.Harga`]}
                                keterangan={
                                    bolehUbahHarga
                                        ? 'Isi 0 bila gratis.'
                                        : 'Perlu izin produk.harga.ubah untuk harga selain 0.'
                                }
                                disabled={!bolehUbahHarga}
                            />
                            <div className="flex items-end">
                                <KotakCentang
                                    label="Aktif di kasir"
                                    nilai={pilihan.Aktif}
                                    saatBerubah={(nilai) => UbahPilihan(indeks, { Aktif: nilai })}
                                />
                            </div>
                        </div>
                        {pilihan.UuidProdukBahan === null ? (
                            <PemilihProduk
                                label={`Bahan terpakai ${String(indeks + 1)} (opsional)`}
                                jenis={JenisBahan}
                                keterangan="Untuk memotong stok bahan, misal topping keju 20 gram."
                                saatPilih={(produk) =>
                                    UbahPilihan(indeks, {
                                        UuidProdukBahan: produk.Uuid,
                                        NamaBahan: `${produk.Nama} (${produk.Satuan.find((s) => s.Uuid === produk.UuidSatuanDasar)?.Simbol ?? ''})`,
                                        Jumlah: '',
                                    })
                                }
                                galat={galat[`Pilihan.${String(indeks)}.UuidProdukBahan`]}
                            />
                        ) : (
                            <div className="grid items-end gap-3 sm:grid-cols-3">
                                <p className="text-isi text-teks-utama sm:col-span-1">
                                    <span className="block text-label font-semibold">Bahan terpakai</span>
                                    {pilihan.NamaBahan ?? 'Bahan'}
                                </p>
                                <BidangJumlah
                                    label={`Jumlah bahan ${String(indeks + 1)} (satuan dasar)`}
                                    nilai={pilihan.Jumlah}
                                    saatBerubah={(nilai) => UbahPilihan(indeks, { Jumlah: nilai })}
                                    galat={galat[`Pilihan.${String(indeks)}.Jumlah`]}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        UbahPilihan(indeks, { UuidProdukBahan: null, Jumlah: '', NamaBahan: null })
                                    }
                                    className="h-10 justify-start text-destructive"
                                >
                                    Lepas bahan
                                </Button>
                            </div>
                        )}
                        {data.Pilihan.length > 1 ? (
                            <p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        formulir.setData((lama) => ({
                                            ...lama,
                                            Pilihan: lama.Pilihan.filter((_, i) => i !== indeks),
                                        }))
                                    }
                                    className="text-destructive"
                                >
                                    Hapus pilihan {pilihan.Nama || String(indeks + 1)}
                                </Button>
                            </p>
                        ) : null}
                    </Card>
                ))}
                {data.Pilihan.length < MaksimalPilihan ? (
                    <p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                formulir.setData((lama) => ({
                                    ...lama,
                                    Pilihan: [
                                        ...lama.Pilihan,
                                        {
                                            Uuid: null,
                                            Nama: '',
                                            Harga: '0',
                                            Aktif: true,
                                            UuidProdukBahan: null,
                                            Jumlah: '',
                                            NamaBahan: null,
                                        },
                                    ],
                                }))
                            }
                        >
                            Tambah pilihan
                        </Button>
                    </p>
                ) : null}
            </FieldSet>
            <div aria-live="polite">
                {(galatLokal ?? galat.Pilihan) ? (
                    <FieldError className="text-keterangan font-semibold">{galatLokal ?? galat.Pilihan}</FieldError>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kelompok pilihan
                </Tombol>
                <Button type="button" variant="outline" onClick={saatSelesai}>
                    Batal
                </Button>
            </div>
        </form>
    );
}

/** Tombol hapus + konfirmasi: kelompok dilepas dari produk yang memakainya. */
function TombolHapusKelompok({ kelompok }: { kelompok: Kelompok }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    aria-label={`Hapus kelompok ${kelompok.Nama}`}
                >
                    Hapus
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus kelompok {kelompok.Nama}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Kelompok ini dilepas dari {kelompok.JumlahProduk} produk. Transaksi lama tidak berubah.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        onClick={() =>
                            router.delete(`/kelola/kelompok-pilihan/${kelompok.Uuid}`, { preserveScroll: true })
                        }
                    >
                        Ya, hapus kelompok
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** F-03 kelompok pilihan (modifier) dengan harga tambahan dan bahan opsional untuk potong stok. */
export default function HalamanDaftarKelompokPilihan({ KelompokPilihan, Izin }: PropsDaftarKelompokPilihan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<Kelompok | 'baru' | null>(null);

    return (
        <TataLetakAplikasi judul="Pilihan (modifier)">
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="kelompok pilihan" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={sunting !== null ? Object.keys(props.errors) : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="max-w-2xl text-isi text-teks-sekunder">
                    Pilihan yang ditanyakan kasir saat menjual, misal Level gula atau Topping. Pasang ke produk dari
                    halaman produk, tab Pilihan.
                </p>
                {Izin.Kelola ? (
                    <Button type="button" onClick={() => AturSunting('baru')}>
                        Tambah kelompok pilihan
                    </Button>
                ) : null}
            </div>
            <Sheet open={sunting !== null} onOpenChange={(buka) => (buka ? undefined : AturSunting(null))}>
                {sunting !== null ? (
                    <SheetContent showCloseButton={false} className="w-full overflow-y-auto sm:max-w-2xl">
                        <SheetHeader>
                            <SheetTitle>
                                {sunting === 'baru' ? 'Tambah kelompok pilihan' : `Ubah kelompok ${sunting.Nama}`}
                            </SheetTitle>
                            <SheetDescription>
                                Atur batas pilih, harga tambahan, dan bahan yang dipotong dari stok.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="px-4 pb-4">
                            <FormKelompok
                                key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                                kelompok={sunting === 'baru' ? null : sunting}
                                bolehUbahHarga={Izin.UbahHarga}
                                saatSelesai={() => AturSunting(null)}
                            />
                        </div>
                    </SheetContent>
                ) : null}
            </Sheet>
            {KelompokPilihan.length === 0 ? (
                <KeadaanKosong judul="Belum ada kelompok pilihan. Tambah kelompok, misal Level gula: Normal, Kurang manis, Tanpa gula." />
            ) : (
                <ul className="flex flex-col gap-3">
                    {KelompokPilihan.map((kelompok) => (
                        <li key={kelompok.Uuid}>
                            <Card className="gap-2 py-4">
                                <CardHeader className="px-4">
                                    <CardTitle className="break-words text-teks-utama">{kelompok.Nama}</CardTitle>
                                    <CardDescription className="text-keterangan">
                                        {RingkasAturanPilih(kelompok.MinimalPilih, kelompok.MaksimalPilih)} · dipakai{' '}
                                        {kelompok.JumlahProduk} produk
                                    </CardDescription>
                                    {Izin.Kelola ? (
                                        <CardAction className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => AturSunting(kelompok)}
                                                aria-label={`Ubah kelompok ${kelompok.Nama}`}
                                            >
                                                Ubah
                                            </Button>
                                            <TombolHapusKelompok kelompok={kelompok} />
                                        </CardAction>
                                    ) : null}
                                </CardHeader>
                                <CardContent className="px-4">
                                    <Table className="text-label">
                                        <TableCaption className="sr-only">Pilihan di {kelompok.Nama}</TableCaption>
                                        <TableHeader className="sr-only">
                                            <TableRow>
                                                <TableHead scope="col">Pilihan</TableHead>
                                                <TableHead scope="col">Bahan</TableHead>
                                                <TableHead scope="col">Tambahan harga</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {AmbilPilihanTampil(kelompok).map((pilihan) => (
                                                <TableRow key={pilihan.Uuid ?? pilihan.Nama}>
                                                    <TableCell className="py-1 pl-0 whitespace-normal text-teks-utama">
                                                        {pilihan.Nama}{' '}
                                                        {!pilihan.Aktif ? (
                                                            <LabelStatus jenis="netral" teks="Nonaktif" />
                                                        ) : null}
                                                    </TableCell>
                                                    <TableCell className="py-1 whitespace-normal text-teks-sekunder">
                                                        {pilihan.NamaProdukBahan
                                                            ? `${pilihan.NamaProdukBahan} ${FormatJumlahSatuan(pilihan.Jumlah, pilihan.SimbolSatuanBahan ?? '')}`
                                                            : ''}
                                                    </TableCell>
                                                    <TableCell className="py-1 pr-0 text-right tabular-nums">
                                                        {CekDesimalValid(pilihan.Harga) &&
                                                        BandingkanDesimal(pilihan.Harga, '0') === 0
                                                            ? 'Gratis'
                                                            : `+${FormatRupiah(pilihan.Harga)}`}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </TataLetakAplikasi>
    );
}

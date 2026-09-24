import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Card } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { DaftarBerhalaman, Pilihan } from '@/Tipe/Pengelola';
import { JenisLabelTagihan, type PembayaranLangganan, type TagihanLangganan } from '@/Tipe/TagihanLangganan';

type BarisAntrean = PembayaranLangganan & {
    NamaTenant: string;
    UuidTagihan: string | null;
    NomorTagihan: string | null;
    TotalTagihan: string | null;
    NamaPaket: string | null;
};

type PropsDaftarTagihan = {
    Antrean: BarisAntrean[];
    Tagihan: DaftarBerhalaman<TagihanLangganan & { NamaTenant: string }>;
    Ringkasan: { MenungguVerifikasi: number; BelumDibayar: number };
    Saring: { Kata: string; Status: string };
    OpsiStatus: Pilihan[];
};

const kelasKepala = 'px-4 text-label font-semibold text-teks-sekunder';

/** Angka ringkasan antrean (kartu ringkas, tanpa warna: angka + teks sudah cukup). */
function KartuRingkasan({ nilai, label }: { nilai: number; label: string }) {
    return (
        <Card className="gap-1 px-4 py-3">
            <span className="text-judul font-semibold tabular-nums text-teks-utama">{nilai}</span>
            <span className="text-label text-teks-sekunder">{label}</span>
        </Card>
    );
}

/** Tagihan langganan & antrean "Menunggu Verifikasi" transfer manual (P-08 langkah 3). */
export default function HalamanDaftarTagihan({ Antrean, Tagihan, Ringkasan, Saring, OpsiStatus }: PropsDaftarTagihan) {
    const [kata, AturKata] = useState(Saring.Kata);
    const [status, AturStatus] = useState(Saring.Status);

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/tagihan', { kata, status }, { preserveState: true, preserveScroll: true });
    };

    return (
        <TataLetakPengelola judul="Tagihan langganan">
            <ul className="grid gap-3 sm:grid-cols-2" aria-label="Ringkasan tagihan">
                <li>
                    <KartuRingkasan nilai={Ringkasan.MenungguVerifikasi} label="bukti transfer menunggu verifikasi" />
                </li>
                <li>
                    <KartuRingkasan nilai={Ringkasan.BelumDibayar} label="tagihan belum dibayar" />
                </li>
            </ul>
            <section aria-labelledby="judul-antrean" className="flex flex-col gap-2">
                <h2 id="judul-antrean" className="text-subjudul font-semibold text-teks-utama">
                    Menunggu verifikasi
                </h2>
                {Antrean.length === 0 ? (
                    <Pemberitahuan jenis="info" judul="Antrean kosong">
                        Belum ada bukti transfer yang perlu diperiksa.
                    </Pemberitahuan>
                ) : (
                    <Card className="gap-0 py-0">
                        <Table className="text-isi">
                            <TableCaption className="sr-only">
                                Bukti transfer menunggu verifikasi, terlama di atas
                            </TableCaption>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead scope="col" className={kelasKepala}>
                                        Diunggah
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Tenant
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Tagihan
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Transfer
                                    </TableHead>
                                    <TableHead scope="col" className={`${kelasKepala} text-right`}>
                                        Jumlah
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Antrean.map((baris) => (
                                    <TableRow key={baris.Uuid} className="align-top">
                                        <TableCell className="px-4">{FormatTanggalWaktu(baris.DiunggahPada)}</TableCell>
                                        <TableCell className="px-4 whitespace-normal">{baris.NamaTenant}</TableCell>
                                        <TableCell className="px-4 whitespace-normal">
                                            {baris.UuidTagihan ? (
                                                <Link
                                                    href={`/tagihan/${baris.UuidTagihan}`}
                                                    className="font-mono text-label text-brand underline"
                                                >
                                                    {baris.NomorTagihan}
                                                </Link>
                                            ) : null}
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {baris.NamaPaket}
                                            </span>
                                        </TableCell>
                                        <TableCell className="px-4 whitespace-normal">
                                            <span className="block">{FormatTanggal(baris.TanggalTransfer)}</span>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {baris.BankPengirim} → {baris.BankTujuan}
                                            </span>
                                        </TableCell>
                                        <TableCell className="px-4 text-right tabular-nums">
                                            {FormatRupiah(baris.Jumlah)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}
            </section>
            <section aria-labelledby="judul-semua" className="flex flex-col gap-2">
                <h2 id="judul-semua" className="text-subjudul font-semibold text-teks-utama">
                    Semua tagihan
                </h2>
                <form onSubmit={Cari} className="flex flex-wrap items-end gap-3">
                    <div className="min-w-64">
                        <BidangTeks label="Cari nomor tagihan atau nama usaha" nilai={kata} saatBerubah={AturKata} />
                    </div>
                    <BidangPilihan
                        label="Status"
                        nilai={status}
                        opsi={OpsiStatus}
                        saatBerubah={AturStatus}
                        kosong="Semua status"
                    />
                    <Tombol type="submit" varian="sekunder">
                        Terapkan
                    </Tombol>
                </form>
                {Tagihan.Data.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada tagihan yang cocok.</p>
                ) : (
                    <Card className="gap-0 py-0">
                        <Table className="text-isi">
                            <TableCaption className="sr-only">Daftar tagihan langganan</TableCaption>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead scope="col" className={kelasKepala}>
                                        Nomor
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Tenant
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Paket
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Jatuh tempo
                                    </TableHead>
                                    <TableHead scope="col" className={`${kelasKepala} text-right`}>
                                        Total
                                    </TableHead>
                                    <TableHead scope="col" className={kelasKepala}>
                                        Status
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Tagihan.Data.map((baris) => (
                                    <TableRow key={baris.Uuid}>
                                        <TableCell className="px-4">
                                            <Link
                                                href={`/tagihan/${baris.Uuid}`}
                                                className="font-mono text-label text-brand underline"
                                            >
                                                {baris.Nomor}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="px-4 whitespace-normal">{baris.NamaTenant}</TableCell>
                                        <TableCell className="px-4 whitespace-normal">
                                            {baris.NamaPaket} · {baris.Siklus}
                                        </TableCell>
                                        <TableCell className="px-4">
                                            {FormatTanggalWaktu(baris.JatuhTempoPada)}
                                        </TableCell>
                                        <TableCell className="px-4 text-right tabular-nums">
                                            {FormatRupiah(baris.Total)}
                                        </TableCell>
                                        <TableCell className="px-4">
                                            <LabelStatus
                                                jenis={JenisLabelTagihan(baris.Status)}
                                                teks={baris.LabelStatus}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}
                <Paginasi
                    alamat="/tagihan"
                    saring={{ kata: Saring.Kata, status: Saring.Status }}
                    halamanSaatIni={Tagihan.HalamanSaatIni}
                    halamanTerakhir={Tagihan.HalamanTerakhir}
                    total={Tagihan.Total}
                    label="Halaman daftar tagihan"
                />
            </section>
        </TataLetakPengelola>
    );
}

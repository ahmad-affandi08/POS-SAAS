import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
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
            <p className="text-isi text-teks-sekunder">
                {Ringkasan.MenungguVerifikasi} bukti transfer menunggu verifikasi · {Ringkasan.BelumDibayar} tagihan
                belum dibayar
            </p>
            <section aria-labelledby="judul-antrean" className="flex flex-col gap-2">
                <h2 id="judul-antrean" className="text-subjudul font-semibold text-teks-utama">
                    Menunggu verifikasi
                </h2>
                {Antrean.length === 0 ? (
                    <Pemberitahuan jenis="info" judul="Antrean kosong">
                        Belum ada bukti transfer yang perlu diperiksa.
                    </Pemberitahuan>
                ) : (
                    <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                        <table className="w-full text-left text-isi">
                            <caption className="sr-only">Bukti transfer menunggu verifikasi, terlama di atas</caption>
                            <thead className="border-b border-garis text-label text-teks-sekunder">
                                <tr>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Diunggah
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Tenant
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Tagihan
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Transfer
                                    </th>
                                    <th scope="col" className="px-4 py-2 text-right font-semibold">
                                        Jumlah
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {Antrean.map((baris) => (
                                    <tr key={baris.Uuid} className="border-b border-garis align-top last:border-b-0">
                                        <td className="px-4 py-2">{FormatTanggalWaktu(baris.DiunggahPada)}</td>
                                        <td className="px-4 py-2">{baris.NamaTenant}</td>
                                        <td className="px-4 py-2">
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
                                        </td>
                                        <td className="px-4 py-2">
                                            <span className="block">{FormatTanggal(baris.TanggalTransfer)}</span>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {baris.BankPengirim} → {baris.BankTujuan}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {FormatRupiah(baris.Jumlah)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
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
                    <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                        <table className="w-full text-left text-isi">
                            <caption className="sr-only">Daftar tagihan langganan</caption>
                            <thead className="border-b border-garis text-label text-teks-sekunder">
                                <tr>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Nomor
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Tenant
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Paket
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Jatuh tempo
                                    </th>
                                    <th scope="col" className="px-4 py-2 text-right font-semibold">
                                        Total
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {Tagihan.Data.map((baris) => (
                                    <tr key={baris.Uuid} className="border-b border-garis last:border-b-0">
                                        <td className="px-4 py-2">
                                            <Link
                                                href={`/tagihan/${baris.Uuid}`}
                                                className="font-mono text-label text-brand underline"
                                            >
                                                {baris.Nomor}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2">{baris.NamaTenant}</td>
                                        <td className="px-4 py-2">
                                            {baris.NamaPaket} · {baris.Siklus}
                                        </td>
                                        <td className="px-4 py-2">{FormatTanggalWaktu(baris.JatuhTempoPada)}</td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {FormatRupiah(baris.Total)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <LabelStatus
                                                jenis={JenisLabelTagihan(baris.Status)}
                                                teks={baris.LabelStatus}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
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

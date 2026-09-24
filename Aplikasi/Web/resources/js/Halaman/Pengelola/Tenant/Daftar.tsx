import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { LabelPenanda, LabelStatusLangganan } from '@/Komponen/Pengelola/Tenant/LabelLangganan';
import { Card } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { DaftarBerhalaman, Pilihan } from '@/Tipe/Pengelola';
import type { BarisTenant } from '@/Tipe/TenantPengelola';

type PropsDaftar = {
    Tenant: DaftarBerhalaman<BarisTenant>;
    Saring: { Kata: string; Status: string; Penanda: string };
    PilihanStatus: Pilihan[];
    PilihanPenanda: Pilihan[];
};

const kelasKepala = 'px-4 text-label font-semibold text-teks-sekunder';

/** Daftar tenant (P-07): cari nama, slug, atau email Owner; saring status langganan & penanda. */
export default function Daftar({ Tenant, Saring, PilihanStatus, PilihanPenanda }: PropsDaftar) {
    const [kata, AturKata] = useState(Saring.Kata);
    const [status, AturStatus] = useState(Saring.Status);
    const [penanda, AturPenanda] = useState(Saring.Penanda);
    const saringAktif = Object.fromEntries(
        Object.entries({ kata: Saring.Kata, status: Saring.Status, penanda: Saring.Penanda }).filter(
            ([, nilai]) => nilai,
        ),
    );
    const adaSaringan = Object.keys(saringAktif).length > 0;

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const kueri = Object.fromEntries(Object.entries({ kata, status, penanda }).filter(([, nilai]) => nilai));
        router.get('/tenant', kueri, { preserveState: true });
    };

    return (
        <TataLetakPengelola judul="Tenant">
            <form onSubmit={Cari} className="grid items-end gap-3 sm:grid-cols-[2fr_1fr_1fr_auto]">
                <BidangTeks
                    label="Cari tenant"
                    nilai={kata}
                    saatBerubah={AturKata}
                    keterangan="Nama usaha, slug, atau email Owner"
                />
                <BidangPilihan
                    label="Status langganan"
                    nilai={status}
                    opsi={PilihanStatus}
                    kosong="Semua status"
                    saatBerubah={AturStatus}
                />
                <BidangPilihan
                    label="Penanda"
                    nilai={penanda}
                    opsi={PilihanPenanda}
                    kosong="Semua tenant"
                    saatBerubah={AturPenanda}
                />
                <Tombol type="submit" varian="sekunder">
                    Terapkan
                </Tombol>
            </form>

            {Tenant.Data.length === 0 ? (
                <Pemberitahuan jenis="info" judul={adaSaringan ? 'Tidak ada tenant yang cocok' : 'Belum ada tenant'}>
                    {adaSaringan
                        ? 'Ubah kata kunci atau saringan, lalu terapkan lagi.'
                        : 'Tenant muncul di sini setelah calon pelanggan mendaftar dari halaman Daftar Gratis.'}
                </Pemberitahuan>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[860px] text-isi">
                        <TableCaption className="sr-only">Daftar tenant, terbaru di atas</TableCaption>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Tenant
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Owner
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Paket & status
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Trial berakhir
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Terdaftar
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Tenant.Data.map((tenant) => (
                                <TableRow key={tenant.Uuid} className="align-top">
                                    <TableCell className="px-4 py-3 whitespace-normal">
                                        <Link
                                            href={`/tenant/${tenant.Uuid}`}
                                            className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            {tenant.Nama}
                                        </Link>
                                        <span className="block font-mono text-keterangan text-teks-sekunder">
                                            {tenant.Slug}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-3 break-all whitespace-normal text-teks-sekunder">
                                        {tenant.EmailPemilik ?? '—'}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 whitespace-normal">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-label">{tenant.KodePaket ?? '—'}</span>
                                            <LabelStatusLangganan status={tenant.StatusLangganan} />
                                            <LabelPenanda penanda={tenant.Penanda} />
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-teks-sekunder">
                                        {tenant.StatusLangganan === 'Trial'
                                            ? FormatTanggalWaktu(tenant.TrialBerakhirPada)
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(tenant.DibuatPada)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <Paginasi
                alamat="/tenant"
                saring={saringAktif}
                halamanSaatIni={Tenant.HalamanSaatIni}
                halamanTerakhir={Tenant.HalamanTerakhir}
                total={Tenant.Total}
                label="Halaman daftar tenant"
            />
        </TataLetakPengelola>
    );
}

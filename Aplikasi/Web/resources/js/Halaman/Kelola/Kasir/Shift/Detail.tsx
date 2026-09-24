import { Link } from '@inertiajs/react';

import LencanaShift from '@/Komponen/Kasir/LencanaShift';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsDetailShift } from '@/Tipe/Kasir';

const kelasKepala = 'h-auto px-4 py-2 text-label font-semibold text-teks-sekunder';

function Nilai({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-label text-teks-sekunder">{label}</dt>
            <dd className="text-isi text-teks-utama">{children}</dd>
        </div>
    );
}

/** F-06: detail shift (baca saja): pembukaan, pecahan kas awal, ringkasan kas non-penjualan, dan mutasi kas. */
export default function HalamanDetailShift({ Shift, MutasiKas }: PropsDetailShift) {
    return (
        <TataLetakAplikasi judul={`Shift ${Shift.NamaKasir} · ${FormatTanggalWaktu(Shift.DibukaPada)}`}>
            <Button asChild variant="link" className="h-auto self-start px-0">
                <Link href="/kelola/kasir/shift">Kembali ke daftar shift</Link>
            </Button>

            {Shift.PerluTinjauan ? (
                <Pemberitahuan jenis="peringatan" judul="Shift ini perlu ditinjau">
                    {Shift.AlasanTinjauan ?? 'Shift ini diterima meski melanggar aturan satu shift terbuka.'}
                </Pemberitahuan>
            ) : null}

            <Card className="gap-4 rounded-panel p-4 shadow-none">
                <LencanaShift
                    status={Shift.Status}
                    label={Shift.LabelStatus}
                    perluTinjauan={Shift.PerluTinjauan}
                    bersama={Shift.Bersama}
                />
                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Nilai label="Outlet">{Shift.NamaOutlet}</Nilai>
                    <Nilai label="Perangkat">
                        <span className="font-mono">{Shift.Perangkat}</span>
                    </Nilai>
                    <Nilai label="Hari bisnis">{FormatTanggal(Shift.TanggalBisnis)}</Nilai>
                    <Nilai label="Diterima server">{FormatTanggalWaktu(Shift.DiterimaPada)}</Nilai>
                    <Nilai label="Kas awal">
                        <span className="tabular-nums">{FormatRupiah(Shift.KasAwal)}</span>
                    </Nilai>
                    <Nilai label="Kas masuk">
                        <span className="tabular-nums">{FormatRupiah(Shift.TotalMasuk)}</span>
                    </Nilai>
                    <Nilai label="Kas keluar + setoran">
                        <span className="tabular-nums">
                            {FormatRupiah(Shift.TotalKeluar)} + {FormatRupiah(Shift.TotalSetoran)}
                        </span>
                    </Nilai>
                    <Nilai label="Kas di laci (tanpa penjualan)">
                        <span className="font-semibold tabular-nums">{FormatRupiah(Shift.KasNonPenjualan)}</span>
                    </Nilai>
                </dl>
                {Shift.PecahanKasAwal.length > 0 ? (
                    <div>
                        <h2 className="text-label font-semibold text-teks-sekunder">Hitungan pecahan kas awal</h2>
                        <ul className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-isi tabular-nums">
                            {Shift.PecahanKasAwal.map((p) => (
                                <li key={p.Nominal}>
                                    {FormatRupiah(p.Nominal)} × {p.Jumlah}
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}
            </Card>

            <h2 className="text-subjudul font-semibold text-teks-utama">Kas masuk, keluar & setoran</h2>
            {MutasiKas.length === 0 ? (
                <KeadaanKosong judul="Belum ada kas masuk, kas keluar, atau setoran di shift ini." />
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className="min-w-[860px] text-left text-isi">
                        <TableCaption className="sr-only">Mutasi kas shift, {MutasiKas.length} baris</TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Waktu
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Jenis & kategori
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Dicatat oleh
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Jurnal
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Jumlah
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {MutasiKas.map((m) => (
                                <TableRow key={m.Uuid} className="border-garis align-top">
                                    <TableCell className="px-4 whitespace-nowrap text-teks-sekunder">
                                        {FormatTanggalWaktu(m.DicatatPada)}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span className="block text-teks-utama">{m.LabelJenis}</span>
                                        {m.NamaKategori ? (
                                            <span className="block text-label text-teks-sekunder">
                                                {m.NamaKategori}
                                            </span>
                                        ) : null}
                                        {m.Catatan ? (
                                            <span className="block text-label break-words text-teks-sekunder">
                                                {m.Catatan}
                                            </span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal">
                                        <span className="block">{m.DicatatOleh}</span>
                                        {m.DisetujuiOleh ? (
                                            <span className="block text-label text-teks-sekunder">
                                                Disetujui {m.DisetujuiOleh}
                                            </span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-nowrap">
                                        {m.UuidJurnal && m.NomorJurnal ? (
                                            <Link
                                                href={`/kelola/akuntansi/jurnal/${m.UuidJurnal}`}
                                                className="font-mono text-brand underline"
                                            >
                                                {m.NomorJurnal}
                                            </Link>
                                        ) : (
                                            <span className="text-teks-sekunder">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'px-4 text-right whitespace-nowrap tabular-nums',
                                            m.Jenis === 'Masuk' ? 'text-sukses' : 'text-teks-utama',
                                        )}
                                    >
                                        {m.Jenis === 'Masuk' ? '+' : '−'}
                                        {FormatRupiah(m.Jumlah)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}
        </TataLetakAplikasi>
    );
}

import { Link, usePage } from '@inertiajs/react';

import { jenisLabelStatusTiket, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant } from '@/Tipe/Organisasi';

type RingkasanTiket = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    LabelKategori: string;
    Status: StatusTiket;
    LabelStatus: string;
    DibuatPada: string;
    PesanTerakhirPada: string | null;
};

type PropsDaftar = {
    Tiket: { Data: RingkasanTiket[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };
    Saring: { Status: 'terbuka' | 'semua' };
};

/** Daftar tiket bantuan tenant (P-09). */
export default function DaftarBantuan({ Tiket, Saring }: PropsDaftar) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.BantuanTiketKelola);
    const tab = [
        { nilai: 'terbuka', label: 'Masih terbuka', href: '/kelola/bantuan' },
        { nilai: 'semua', label: 'Semua tiket', href: '/kelola/bantuan?status=semua' },
    ] as const;

    return (
        <TataLetakAplikasi judul="Bantuan">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Ada kendala? Kirim tiket ke Tim Dukungan dan pantau balasannya di sini.
                </p>
                {bolehKelola ? (
                    <Button asChild>
                        <Link href="/kelola/bantuan/buat">Buat tiket</Link>
                    </Button>
                ) : null}
            </div>

            {/* Saringan adalah tautan halaman (bukan panel Tabs), jadi tetap <nav> + aria-current. */}
            <nav aria-label="Saring tiket" className="flex gap-1 border-b border-garis">
                {tab.map((item) => (
                    <Button
                        key={item.nilai}
                        asChild
                        variant="ghost"
                        className={cn(
                            '-mb-px rounded-none border-b-2 font-semibold',
                            Saring.Status === item.nilai
                                ? 'border-primary text-teks-utama'
                                : 'border-transparent text-teks-sekunder',
                        )}
                    >
                        <Link href={item.href} aria-current={Saring.Status === item.nilai ? 'page' : undefined}>
                            {item.label}
                        </Link>
                    </Button>
                ))}
            </nav>

            {Tiket.Data.length === 0 ? (
                <Empty className="items-start border border-solid border-garis bg-permukaan p-6 text-left md:p-6">
                    <EmptyHeader className="max-w-none items-start text-left">
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            {Saring.Status === 'terbuka'
                                ? 'Tidak ada tiket yang masih terbuka.'
                                : 'Belum ada tiket. Buat tiket bila Anda butuh bantuan.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[720px] text-isi">
                        <TableCaption className="sr-only">Tiket bantuan, terbaru di atas</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col" className="px-4">
                                    Nomor
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Judul
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Status
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Pesan terakhir
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Tiket.Data.map((tiket) => (
                                <TableRow key={tiket.Uuid} className="align-top">
                                    <TableCell className="px-4 py-3 font-mono text-label">
                                        <Link
                                            href={`/kelola/bantuan/${tiket.Uuid}`}
                                            className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            {tiket.Nomor}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="px-4 py-3 whitespace-normal text-teks-utama">
                                        <span className="block break-words">{tiket.Judul}</span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            {tiket.LabelKategori}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
                                        <LabelStatus
                                            jenis={jenisLabelStatusTiket[tiket.Status]}
                                            teks={tiket.LabelStatus}
                                        />
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(tiket.PesanTerakhirPada ?? tiket.DibuatPada)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <Paginasi
                alamat="/kelola/bantuan"
                saring={Saring.Status === 'semua' ? { status: 'semua' } : {}}
                halamanSaatIni={Tiket.HalamanSaatIni}
                halamanTerakhir={Tiket.HalamanTerakhir}
                total={Tiket.Total}
                label="Halaman tiket"
            />
        </TataLetakAplikasi>
    );
}

import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type Log = {
    Id: number;
    Peristiwa: string;
    Pelaku: string;
    JenisObjek: string | null;
    IdObjek: number | null;
    NilaiLama: Record<string, unknown> | null;
    NilaiBaru: Record<string, unknown> | null;
    Ip: string | null;
    DibuatPada: string;
};

type PropsDaftar = {
    Log: { Data: Log[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };
    Saring: { Kata: string };
};

const kelasKepala = 'px-4 text-label font-semibold text-teks-sekunder';

/** Log audit usaha, hanya baca (aturan LogAudit §13.2): siapa melakukan apa, kapan, dari mana. */
export default function HalamanLogAudit({ Log, Saring }: PropsDaftar) {
    const [kata, AturKata] = useState(Saring.Kata);

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/kelola/log-audit', kata ? { kata } : {}, { preserveState: true });
    };

    return (
        <TataLetakAplikasi judul="Log audit">
            <form onSubmit={Cari} className="flex flex-wrap items-end gap-2">
                <div className="w-full max-w-sm">
                    <BidangTeks
                        label="Cari peristiwa"
                        nilai={kata}
                        saatBerubah={AturKata}
                        keterangan="Misal: outlet, pengguna.undang, atau sesi.masuk"
                    />
                </div>
                <Tombol type="submit" varian="sekunder">
                    Cari
                </Tombol>
            </form>

            {Log.Data.length === 0 ? (
                <Empty className="border border-garis bg-permukaan p-6 md:p-6">
                    <EmptyHeader>
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            {Saring.Kata
                                ? `Tidak ada log dengan peristiwa "${Saring.Kata}".`
                                : 'Belum ada aktivitas yang tercatat.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[860px] text-isi">
                        <TableCaption className="sr-only">Log audit usaha, terbaru di atas</TableCaption>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Waktu
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Pelaku
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Peristiwa
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Objek
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Perubahan
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    IP
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Log.Data.map((log) => (
                                <TableRow key={log.Id} className="align-top">
                                    <TableCell className="px-4 text-teks-sekunder">
                                        {FormatTanggalWaktu(log.DibuatPada)}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal text-teks-utama">{log.Pelaku}</TableCell>
                                    <TableCell className="px-4 font-mono text-label text-teks-utama">
                                        {log.Peristiwa}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal text-teks-sekunder">
                                        {log.JenisObjek ? `${log.JenisObjek} #${String(log.IdObjek ?? '')}` : '—'}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal text-keterangan text-teks-sekunder">
                                        {log.NilaiLama ? (
                                            <p>
                                                Lama:{' '}
                                                <code className="font-mono break-all">
                                                    {JSON.stringify(log.NilaiLama)}
                                                </code>
                                            </p>
                                        ) : null}
                                        {log.NilaiBaru ? (
                                            <p>
                                                Baru:{' '}
                                                <code className="font-mono break-all">
                                                    {JSON.stringify(log.NilaiBaru)}
                                                </code>
                                            </p>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 font-mono text-keterangan text-teks-sekunder">
                                        {log.Ip ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <Paginasi
                alamat="/kelola/log-audit"
                saring={Saring.Kata ? { kata: Saring.Kata } : {}}
                halamanSaatIni={Log.HalamanSaatIni}
                halamanTerakhir={Log.HalamanTerakhir}
                total={Log.Total}
                label="Halaman log audit"
            />
        </TataLetakAplikasi>
    );
}

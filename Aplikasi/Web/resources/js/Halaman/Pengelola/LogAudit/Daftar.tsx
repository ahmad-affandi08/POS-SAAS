import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';

type Log = {
    Id: number;
    Aksi: string;
    Pelaku: string;
    JenisObjek: string | null;
    IdObjek: number | null;
    IdTenant: number | null;
    NilaiLama: Record<string, unknown> | null;
    NilaiBaru: Record<string, unknown> | null;
    Alasan: string | null;
    Ip: string | null;
    DibuatPada: string;
};

type PropsDaftar = {
    Log: { Data: Log[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };
    Saring: { Kata: string };
};

const kelasKepala = 'px-4 text-label font-semibold text-teks-sekunder';

/** Log audit Platform Pengelola, hanya baca (BR-P01.3). */
export default function Daftar({ Log, Saring }: PropsDaftar) {
    const [kata, AturKata] = useState(Saring.Kata);

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/log-audit', kata ? { kata } : {}, { preserveState: true });
    };

    return (
        <TataLetakPengelola judul="Log audit">
            <form onSubmit={Cari} className="flex flex-wrap items-end gap-2">
                <div className="w-full max-w-sm">
                    <BidangTeks
                        label="Cari aksi"
                        nilai={kata}
                        saatBerubah={AturKata}
                        keterangan="Misal: tim.anggota atau sesi.masuk"
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
                                ? `Tidak ada log dengan aksi "${Saring.Kata}".`
                                : 'Belum ada aktivitas yang tercatat.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[860px] text-isi">
                        <TableCaption className="sr-only">Log audit pengelola, terbaru di atas</TableCaption>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Waktu
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Pelaku
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Aksi
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
                                    <TableCell className="px-4 py-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(log.DibuatPada)}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 whitespace-normal text-teks-utama">
                                        {log.Pelaku}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 font-mono text-label text-teks-utama">
                                        {log.Aksi}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 whitespace-normal text-teks-sekunder">
                                        {log.JenisObjek ? `${log.JenisObjek} #${String(log.IdObjek ?? '')}` : '—'}
                                        {log.IdTenant !== null ? (
                                            <span className="block">Tenant #{log.IdTenant}</span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 whitespace-normal text-keterangan text-teks-sekunder">
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
                                        {log.Alasan ? <p>Alasan: {log.Alasan}</p> : null}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 font-mono text-keterangan text-teks-sekunder">
                                        {log.Ip ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <Paginasi
                alamat="/log-audit"
                saring={Saring.Kata ? { kata: Saring.Kata } : {}}
                halamanSaatIni={Log.HalamanSaatIni}
                halamanTerakhir={Log.HalamanTerakhir}
                total={Log.Total}
                label="Halaman log audit"
            />
        </TataLetakPengelola>
    );
}

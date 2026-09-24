import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { jenisLabelStatusTiket, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { DaftarBerhalaman, Pilihan } from '@/Tipe/Pengelola';

type BarisAntrean = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    NamaTenant: string;
    Kategori: string;
    Prioritas: 'Mendesak' | 'Tinggi' | 'Normal' | 'Rendah';
    LabelPrioritas: string;
    Status: StatusTiket;
    LabelStatus: string;
    PenanggungJawab: string | null;
    BatasSlaPada: string;
    ResponsPertamaPada: string | null;
    LewatSla: boolean;
    PesanTerakhirPada: string | null;
};

type Saring = { Status: string; Prioritas: string; LewatSla: boolean; Milik: string; Kata: string };

type PropsAntrean = {
    Tiket: DaftarBerhalaman<BarisAntrean>;
    Saring: Saring;
    PilihanStatus: Pilihan[];
    PilihanPrioritas: Pilihan[];
};

const kelasKepala = 'px-3 text-label font-semibold text-teks-sekunder';

const jenisLabelPrioritas = { Mendesak: 'bahaya', Tinggi: 'peringatan', Normal: 'netral', Rendah: 'netral' } as const;

function BuatQuery(saring: Saring): Record<string, string> {
    return Object.fromEntries(
        Object.entries({
            status: saring.Status === 'terbuka' ? '' : saring.Status,
            prioritas: saring.Prioritas,
            'lewat-sla': saring.LewatSla ? '1' : '',
            milik: saring.Milik,
            kata: saring.Kata,
        }).filter(([, nilai]) => nilai !== ''),
    );
}

/** Antrean tiket dukungan semua tenant (P-09). Tiket terbuka diurutkan dari batas SLA terdekat. */
export default function AntreanTiket({ Tiket, Saring, PilihanStatus, PilihanPrioritas }: PropsAntrean) {
    const [saring, AturSaring] = useState<Saring>(Saring);
    const Terapkan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/dukungan/tiket', BuatQuery(saring), { preserveState: true });
    };

    return (
        <TataLetakPengelola judul="Tiket dukungan">
            <Card className="py-4">
                <form
                    onSubmit={Terapkan}
                    className="grid grid-cols-1 items-end gap-3 px-4 sm:grid-cols-2 lg:grid-cols-6"
                >
                    <BidangPilihan
                        label="Status"
                        nilai={saring.Status}
                        opsi={[
                            { Nilai: 'terbuka', Label: 'Semua yang terbuka' },
                            { Nilai: 'semua', Label: 'Semua status' },
                            ...PilihanStatus,
                        ]}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Status: nilai })}
                    />
                    <BidangPilihan
                        label="Prioritas"
                        nilai={saring.Prioritas}
                        opsi={PilihanPrioritas}
                        kosong="Semua prioritas"
                        saatBerubah={(nilai) => AturSaring({ ...saring, Prioritas: nilai })}
                    />
                    <BidangPilihan
                        label="Penanggung jawab"
                        nilai={saring.Milik}
                        opsi={[
                            { Nilai: 'saya', Label: 'Tiket saya' },
                            { Nilai: 'belum', Label: 'Belum ada' },
                        ]}
                        kosong="Semua"
                        saatBerubah={(nilai) => AturSaring({ ...saring, Milik: nilai })}
                    />
                    <BidangTeks
                        label="Cari nomor/judul"
                        nilai={saring.Kata}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Kata: nilai })}
                    />
                    <KotakCentang
                        label="Hanya lewat SLA"
                        nilai={saring.LewatSla}
                        saatBerubah={(nilai) => AturSaring({ ...saring, LewatSla: nilai })}
                    />
                    <Tombol type="submit" varian="sekunder">
                        Terapkan saringan
                    </Tombol>
                </form>
            </Card>

            {Tiket.Data.length === 0 ? (
                <Empty className="border border-garis bg-permukaan p-6 md:p-6">
                    <EmptyHeader>
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            Tidak ada tiket yang cocok dengan saringan ini.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[960px] text-isi">
                        <TableCaption className="sr-only">Antrean tiket dukungan</TableCaption>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Nomor
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Judul & tenant
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Prioritas
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Status
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Penanggung jawab
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Batas respons (SLA)
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Pesan terakhir
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Tiket.Data.map((tiket) => (
                                <TableRow key={tiket.Uuid} className="align-top">
                                    <TableCell className="px-3 font-mono text-label">
                                        <Link
                                            href={`/dukungan/tiket/${tiket.Uuid}`}
                                            className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            {tiket.Nomor}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="px-3 whitespace-normal">
                                        <span className="block break-words text-teks-utama">{tiket.Judul}</span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            {tiket.NamaTenant} · {tiket.Kategori}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-3">
                                        <LabelStatus
                                            jenis={jenisLabelPrioritas[tiket.Prioritas]}
                                            teks={tiket.LabelPrioritas}
                                        />
                                    </TableCell>
                                    <TableCell className="px-3">
                                        <LabelStatus
                                            jenis={jenisLabelStatusTiket[tiket.Status]}
                                            teks={tiket.LabelStatus}
                                        />
                                    </TableCell>
                                    <TableCell className="px-3 whitespace-normal text-teks-utama">
                                        {tiket.PenanggungJawab ?? 'Belum ada'}
                                    </TableCell>
                                    <TableCell className="px-3 text-teks-sekunder">
                                        <span className="block">{FormatTanggalWaktu(tiket.BatasSlaPada)}</span>
                                        {tiket.LewatSla ? (
                                            <LabelStatus jenis="bahaya" teks="Lewat SLA" />
                                        ) : tiket.ResponsPertamaPada ? (
                                            <span className="text-keterangan">Sudah direspons</span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(tiket.PesanTerakhirPada)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}

            <Paginasi
                alamat="/dukungan/tiket"
                saring={BuatQuery(Saring)}
                halamanSaatIni={Tiket.HalamanSaatIni}
                halamanTerakhir={Tiket.HalamanTerakhir}
                total={Tiket.Total}
                label="Halaman antrean tiket"
            />
        </TataLetakPengelola>
    );
}

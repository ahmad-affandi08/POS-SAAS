import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import KerangkaMemuat from '@/Komponen/Persediaan/KerangkaMemuat';
import LabelStatusStokAwal from '@/Komponen/Persediaan/LabelStatusStokAwal';
import PanelKesiapanAkun from '@/Komponen/Persediaan/PanelKesiapanAkun';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatLabelGudang, FormatNilai } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDaftarStokAwal, SaringStokAwal } from '@/Tipe/Persediaan';

export const AlamatStokAwal = '/kelola/persediaan/stok-awal';

const opsiStatus: { Nilai: SaringStokAwal['Status']; Label: string }[] = [
    { Nilai: 'Semua', Label: 'Semua status' },
    { Nilai: 'Draf', Label: 'Draf' },
    { Nilai: 'Memproses', Label: 'Sedang diposting' },
    { Nilai: 'Diposting', Label: 'Diposting' },
    { Nilai: 'Dibatalkan', Label: 'Dibatalkan' },
    { Nilai: 'Dibuang', Label: 'Dibuang' },
];

/** Parameter query daftar stok awal (`?kata=&status=&gudang=&halaman=`); nilai kosong dan bawaan tidak dikirim. */
export function BuatQueryStokAwal(saring: SaringStokAwal): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.Kata.trim() !== '') {
        query.kata = saring.Kata.trim();
    }

    if (saring.Status !== 'Semua') {
        query.status = saring.Status;
    }

    if (saring.UuidGudang) {
        query.gudang = saring.UuidGudang;
    }

    return query;
}

/** F-05a: daftar dokumen stok awal per lokasi stok dengan saringan status/lokasi dan ajakan buat/impor. */
export default function HalamanDaftarStokAwal({
    StokAwal,
    Saring,
    OpsiGudang,
    Izin,
    KesiapanAkun,
}: PropsDaftarStokAwal) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [saring, AturSaring] = useState<SaringStokAwal>(Saring);
    const [memuat, AturMemuat] = useState(false);
    const adaSaringan = Object.keys(BuatQueryStokAwal(Saring)).length > 0;
    const kelasKepala = 'px-4 py-2 h-auto text-label font-semibold text-teks-sekunder';

    const Terapkan = (baru: SaringStokAwal) =>
        router.get(AlamatStokAwal, BuatQueryStokAwal(baru), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });

    const Ubah = <K extends keyof SaringStokAwal>(kunci: K, nilai: SaringStokAwal[K]) => {
        const baru = { ...saring, [kunci]: nilai };
        AturSaring(baru);

        if (kunci !== 'Kata') {
            Terapkan(baru);
        }
    };

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        Terapkan(saring);
    };

    return (
        <TataLetakAplikasi judul="Stok awal">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-2xl text-isi text-teks-sekunder">
                    Catat jumlah dan harga modal barang yang sudah ada saat mulai memakai aplikasi. Setelah diposting,
                    stok dan HPP tercatat serta jurnal saldo awal dibuat otomatis.
                </p>
                {Izin.Kelola ? (
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="h-10">
                            <Link href={`${AlamatStokAwal}/impor`}>Impor dari Excel</Link>
                        </Button>
                        <Button asChild className="h-10">
                            <Link href={`${AlamatStokAwal}/buat`}>Buat stok awal</Link>
                        </Button>
                    </div>
                ) : null}
            </div>

            {Izin.PostingStokAwal ? <PanelKesiapanAkun kesiapan={KesiapanAkun} /> : null}
            {!Izin.Kelola ? <PesanHanyaLihat izin="persediaan.kelola" objek="dokumen stok awal" /> : null}
            <DaftarGalatServer galat={props.errors} />

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Kirim}
                    role="search"
                    aria-label="Cari dan saring stok awal"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="sm:col-span-2">
                        <BidangTeks
                            label="Cari stok awal"
                            nilai={saring.Kata}
                            saatBerubah={(nilai) => Ubah('Kata', nilai)}
                            keterangan="Nomor dokumen atau catatan. Tekan Enter untuk mencari."
                            maxLength={100}
                        />
                    </div>
                    <BidangPilihan
                        label="Status"
                        nilai={saring.Status}
                        opsi={opsiStatus}
                        saatBerubah={(nilai) => Ubah('Status', nilai as SaringStokAwal['Status'])}
                    />
                    <BidangPilihan
                        label="Lokasi stok"
                        nilai={saring.UuidGudang ?? ''}
                        kosong="Semua lokasi"
                        opsi={OpsiGudang.map((gudang) => ({ Nilai: gudang.Uuid, Label: FormatLabelGudang(gudang) }))}
                        saatBerubah={(nilai) => Ubah('UuidGudang', nilai === '' ? null : nilai)}
                    />
                    <div className="sm:col-span-2 lg:col-span-4">
                        <Button type="submit" variant="outline" className="h-10">
                            Cari
                        </Button>
                    </div>
                </form>
            </Card>

            <div aria-live="polite" className="sr-only">
                {memuat ? 'Memuat stok awal…' : `${String(StokAwal.Total)} dokumen stok awal ditemukan.`}
            </div>

            {memuat ? (
                <KerangkaMemuat label="Memuat stok awal…" />
            ) : StokAwal.Data.length === 0 ? (
                adaSaringan ? (
                    <KeadaanKosong judul="Tidak ada stok awal yang cocok dengan pencarian atau saringan ini.">
                        <Button asChild variant="link" className="h-auto px-0">
                            <Link href={AlamatStokAwal}>Hapus saringan</Link>
                        </Button>
                    </KeadaanKosong>
                ) : (
                    <KeadaanKosong judul="Belum ada stok awal. Isi stok awal agar saldo stok dan HPP benar sejak hari pertama.">
                        {Izin.Kelola ? (
                            <>
                                <Button asChild variant="outline">
                                    <Link href={`${AlamatStokAwal}/impor`}>Impor dari Excel</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={`${AlamatStokAwal}/buat`}>Buat stok awal</Link>
                                </Button>
                            </>
                        ) : (
                            <span>Minta pengelola persediaan mengisi stok awal.</span>
                        )}
                    </KeadaanKosong>
                )
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className="min-w-[860px] text-left text-isi">
                        <TableCaption className="sr-only">Daftar stok awal, {StokAwal.Total} dokumen</TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Nomor
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Tanggal
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Lokasi stok
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Status
                                </TableHead>
                                <TableHead scope="col" className={`${kelasKepala} text-right`}>
                                    Baris
                                </TableHead>
                                <TableHead scope="col" className={`${kelasKepala} text-right`}>
                                    Total nilai
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Terakhir diubah
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {StokAwal.Data.map((dokumen) => (
                                <TableRow key={dokumen.Uuid} className="border-garis align-top">
                                    <TableCell className="px-4 py-2 whitespace-normal">
                                        <Link
                                            href={`${AlamatStokAwal}/${dokumen.Uuid}`}
                                            className="font-mono font-semibold break-all text-brand underline"
                                        >
                                            {dokumen.Nomor ?? 'Draf tanpa nomor'}
                                        </Link>
                                        {dokumen.Sumber === 'Impor' ? (
                                            <span className="block text-keterangan text-teks-sekunder">
                                                Dari impor Excel
                                            </span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 py-2 whitespace-nowrap">
                                        {FormatTanggal(dokumen.Tanggal)}
                                    </TableCell>
                                    <TableCell className="px-4 py-2 whitespace-normal">
                                        <span className="block break-words text-teks-utama">{dokumen.NamaGudang}</span>
                                        {dokumen.NamaOutlet ? (
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {dokumen.NamaOutlet}
                                            </span>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="px-4 py-2">
                                        <LabelStatusStokAwal status={dokumen.Status} label={dokumen.LabelStatus} />
                                    </TableCell>
                                    <TableCell className="px-4 py-2 text-right tabular-nums">
                                        {dokumen.JumlahBaris.toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell className="px-4 py-2 text-right whitespace-nowrap tabular-nums">
                                        {FormatNilai(dokumen.TotalNilai)}
                                    </TableCell>
                                    <TableCell className="px-4 py-2 whitespace-normal text-teks-sekunder">
                                        {FormatTanggalWaktu(dokumen.DiubahPada)}
                                        {dokumen.DibuatOleh ? (
                                            <span className="block text-keterangan">Dibuat {dokumen.DibuatOleh}</span>
                                        ) : null}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}

            <Paginasi
                alamat={AlamatStokAwal}
                saring={BuatQueryStokAwal(Saring)}
                halamanSaatIni={StokAwal.HalamanSaatIni}
                halamanTerakhir={StokAwal.HalamanTerakhir}
                total={StokAwal.Total}
                label="Halaman daftar stok awal"
            />
        </TataLetakAplikasi>
    );
}

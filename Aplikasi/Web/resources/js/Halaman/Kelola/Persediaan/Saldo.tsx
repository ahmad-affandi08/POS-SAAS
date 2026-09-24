import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import SakelarPadat, { KelasSel, usePadatTabel } from '@/Komponen/Katalog/SakelarPadat';
import KerangkaMemuat from '@/Komponen/Persediaan/KerangkaMemuat';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import {
    AmbilLabelMetodeHpp,
    AmbilLabelPelacakan,
    FormatHppSatuan,
    FormatJumlahStok,
    FormatLabelGudang,
    FormatNilai,
} from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { AmbilTandaDesimal } from '@/Pustaka/HitungDesimal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant } from '@/Tipe/Organisasi';
import type { BarisSaldoStok, KeadaanSaldo, PropsSaldoStok } from '@/Tipe/Persediaan';

const alamat = '/kelola/persediaan/saldo';
/** Batch yang tampil langsung per baris; sisanya diringkas "dan N batch lain" (data ekstrem). */
const batasBatchTampil = 3;

type SaringSaldo = PropsSaldoStok['Saring'];

const opsiKeadaan: { Nilai: KeadaanSaldo; Label: string }[] = [
    { Nilai: 'Semua', Label: 'Semua saldo' },
    { Nilai: 'Ada', Label: 'Ada stok' },
    { Nilai: 'Nol', Label: 'Stok nol' },
    { Nilai: 'Minus', Label: 'Stok minus' },
];

const opsiUrut: { Nilai: SaringSaldo['Urut']; Label: string }[] = [
    { Nilai: 'Nama', Label: 'Nama produk (A–Z)' },
    { Nilai: '-Nilai', Label: 'Nilai terbesar' },
    { Nilai: 'Jumlah', Label: 'Jumlah terkecil' },
];

/** Parameter query saldo stok (`?kata=&gudang=&keadaan=&urut=&halaman=`); nilai kosong dan bawaan tidak dikirim. */
export function BuatQuerySaldo(saring: SaringSaldo): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.Kata.trim() !== '') {
        query.kata = saring.Kata.trim();
    }

    if (saring.UuidGudang) {
        query.gudang = saring.UuidGudang;
    }

    if (saring.Keadaan !== 'Semua') {
        query.keadaan = saring.Keadaan;
    }

    if (saring.Urut !== 'Nama') {
        query.urut = saring.Urut;
    }

    return query;
}

function RincianPelacakan({ saldo }: { saldo: BarisSaldoStok }) {
    if (saldo.Pelacakan === 'Seri') {
        return (
            <span className="block text-keterangan text-teks-sekunder">
                {(saldo.JumlahNomorSeri ?? 0).toLocaleString('id-ID')} nomor seri tersedia
            </span>
        );
    }

    if (saldo.Pelacakan !== 'Batch' || saldo.Batch.length === 0) {
        return null;
    }

    const sisa = saldo.Batch.length - batasBatchTampil;

    return (
        <ul className="text-keterangan text-teks-sekunder" aria-label={`Batch ${saldo.NamaProduk}`}>
            {saldo.Batch.slice(0, batasBatchTampil).map((batch) => (
                <li key={batch.NomorBatch}>
                    <span className="font-mono">{batch.NomorBatch}</span> ·{' '}
                    {batch.TanggalKedaluwarsa
                        ? `kedaluwarsa ${FormatTanggal(batch.TanggalKedaluwarsa)}`
                        : 'tanpa tanggal'}{' '}
                    · {FormatJumlahStok(batch.JumlahSisa, saldo.SimbolSatuan)}
                </li>
            ))}
            {sisa > 0 ? <li>dan {sisa.toLocaleString('id-ID')} batch lain (lihat kartu stok)</li> : null}
        </ul>
    );
}

/** F-05a: saldo stok per produk per lokasi (cache `SaldoStok`), nilai persediaan, dan tautan kartu stok. */
export default function HalamanSaldoStok({ Saldo, Ringkasan, Saring, OpsiGudang, MetodeHpp }: PropsSaldoStok) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.PersediaanKelola);
    const [saring, AturSaring] = useState<SaringSaldo>(Saring);
    const [memuat, AturMemuat] = useState(false);
    const [padat, AturPadat] = usePadatTabel('SaldoStok');
    const adaSaringan = Object.keys(BuatQuerySaldo({ ...Saring, Urut: 'Nama' })).length > 0;
    const sel = KelasSel(padat);
    const kelasKepala = cn(sel, 'h-auto text-label font-semibold text-teks-sekunder');

    const Terapkan = (baru: SaringSaldo) =>
        router.get(alamat, BuatQuerySaldo(baru), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });

    const Ubah = <K extends keyof SaringSaldo>(kunci: K, nilai: SaringSaldo[K]) => {
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
        <TataLetakAplikasi judul="Saldo stok">
            <section aria-label="Ringkasan saldo stok" className="grid gap-3 sm:grid-cols-3">
                <Card className="gap-1 rounded-panel p-4 shadow-none">
                    <span className="text-label font-semibold text-teks-sekunder">Total nilai persediaan</span>
                    <span className="text-subjudul font-semibold break-all text-teks-utama tabular-nums">
                        {FormatNilai(Ringkasan.TotalNilai)}
                    </span>
                    <span className="text-keterangan text-teks-sekunder">
                        Metode HPP: {AmbilLabelMetodeHpp(MetodeHpp)}
                    </span>
                </Card>
                <Card className="gap-1 rounded-panel p-4 shadow-none">
                    <span className="text-label font-semibold text-teks-sekunder">Produk × lokasi</span>
                    <span className="text-subjudul font-semibold text-teks-utama tabular-nums">
                        {Ringkasan.JumlahBaris.toLocaleString('id-ID')}
                    </span>
                </Card>
                <Card className="gap-1 rounded-panel p-4 shadow-none">
                    <span className="text-label font-semibold text-teks-sekunder">Stok minus</span>
                    <span
                        className={cn(
                            'text-subjudul font-semibold tabular-nums',
                            Ringkasan.JumlahMinus > 0 ? 'text-bahaya' : 'text-teks-utama',
                        )}
                    >
                        {Ringkasan.JumlahMinus.toLocaleString('id-ID')}
                    </span>
                    {Ringkasan.JumlahMinus > 0 ? (
                        <button
                            type="button"
                            onClick={() => Ubah('Keadaan', 'Minus')}
                            className="self-start text-keterangan font-semibold text-brand underline"
                        >
                            Tampilkan stok minus
                        </button>
                    ) : null}
                </Card>
            </section>

            <DaftarGalatServer galat={props.errors} />

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Kirim}
                    role="search"
                    aria-label="Cari dan saring saldo stok"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="sm:col-span-2">
                        <BidangTeks
                            label="Cari produk"
                            nilai={saring.Kata}
                            saatBerubah={(nilai) => Ubah('Kata', nilai)}
                            keterangan="Nama atau SKU. Tekan Enter untuk mencari."
                            maxLength={100}
                        />
                    </div>
                    <BidangPilihan
                        label="Lokasi stok"
                        nilai={saring.UuidGudang ?? ''}
                        kosong="Semua lokasi"
                        opsi={OpsiGudang.map((gudang) => ({ Nilai: gudang.Uuid, Label: FormatLabelGudang(gudang) }))}
                        saatBerubah={(nilai) => Ubah('UuidGudang', nilai === '' ? null : nilai)}
                    />
                    <BidangPilihan
                        label="Keadaan stok"
                        nilai={saring.Keadaan}
                        opsi={opsiKeadaan}
                        saatBerubah={(nilai) => Ubah('Keadaan', nilai as KeadaanSaldo)}
                    />
                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <div className="w-full sm:w-56">
                            <BidangPilihan
                                label="Urutkan"
                                nilai={saring.Urut}
                                opsi={opsiUrut}
                                saatBerubah={(nilai) => Ubah('Urut', nilai as SaringSaldo['Urut'])}
                            />
                        </div>
                        <Button type="submit" variant="outline" className="h-10">
                            Cari
                        </Button>
                        <SakelarPadat padat={padat} saatBerubah={AturPadat} />
                    </div>
                </form>
            </Card>

            <div aria-live="polite" className="sr-only">
                {memuat ? 'Memuat saldo stok…' : `${String(Saldo.Total)} saldo ditemukan.`}
            </div>

            {memuat ? (
                <KerangkaMemuat label="Memuat saldo stok…" />
            ) : Saldo.Data.length === 0 ? (
                adaSaringan ? (
                    <KeadaanKosong judul="Tidak ada saldo yang cocok dengan pencarian atau saringan ini.">
                        <Button asChild variant="link" className="h-auto px-0">
                            <Link href={alamat}>Hapus saringan</Link>
                        </Button>
                    </KeadaanKosong>
                ) : (
                    <KeadaanKosong judul="Belum ada stok tercatat. Isi stok awal agar saldo dan HPP benar sejak hari pertama.">
                        {bolehKelola ? (
                            <Button asChild>
                                <Link href="/kelola/persediaan/stok-awal/buat">Buat stok awal</Link>
                            </Button>
                        ) : (
                            <span>Minta pengelola persediaan mengisi stok awal.</span>
                        )}
                    </KeadaanKosong>
                )
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className={cn('min-w-[900px] text-left', padat ? 'text-label' : 'text-isi')}>
                        <TableCaption className="sr-only">Saldo stok, {Saldo.Total} baris</TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Produk
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Lokasi stok
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Jumlah tersedia
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    HPP rata-rata
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Nilai persediaan
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    <span className="sr-only">Kartu stok</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Saldo.Data.map((saldo) => {
                                const tanda = AmbilTandaDesimal(saldo.JumlahTersedia);
                                const pelacakan = AmbilLabelPelacakan(saldo.Pelacakan);

                                return (
                                    <TableRow
                                        key={`${saldo.UuidProduk}-${saldo.UuidGudang}`}
                                        className="border-garis align-top"
                                    >
                                        <TableCell className={cn(sel, 'whitespace-normal')}>
                                            <span className="block font-semibold break-words text-teks-utama">
                                                {saldo.NamaProduk}
                                            </span>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                <span className="font-mono">{saldo.Sku ?? 'Tanpa SKU'}</span>
                                                {pelacakan ? ` · ${pelacakan}` : null}
                                            </span>
                                            {!padat ? <RincianPelacakan saldo={saldo} /> : null}
                                        </TableCell>
                                        <TableCell className={cn(sel, 'whitespace-normal')}>
                                            <span className="block break-words text-teks-utama">
                                                {saldo.NamaGudang}
                                            </span>
                                            {saldo.NamaOutlet ? (
                                                <span className="block text-keterangan text-teks-sekunder">
                                                    {saldo.NamaOutlet}
                                                </span>
                                            ) : null}
                                            {!saldo.GudangAktif ? (
                                                <LabelStatus jenis="netral" teks="Diarsipkan" />
                                            ) : null}
                                        </TableCell>
                                        <TableCell className={cn(sel, 'text-right whitespace-nowrap tabular-nums')}>
                                            <span className={tanda < 0 ? 'font-semibold text-bahaya' : undefined}>
                                                {FormatJumlahStok(saldo.JumlahTersedia, saldo.SimbolSatuan)}
                                            </span>
                                            {tanda < 0 ? (
                                                <span className="block">
                                                    <LabelStatus jenis="bahaya" teks="Minus" />
                                                </span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell className={cn(sel, 'text-right whitespace-nowrap tabular-nums')}>
                                            {saldo.HppRataRata === null ? (
                                                <span className="text-teks-sekunder">Belum diketahui</span>
                                            ) : (
                                                FormatHppSatuan(saldo.HppRataRata)
                                            )}
                                        </TableCell>
                                        <TableCell className={cn(sel, 'text-right whitespace-nowrap tabular-nums')}>
                                            {FormatNilai(saldo.NilaiPersediaan)}
                                        </TableCell>
                                        <TableCell className={cn(sel, 'text-right whitespace-nowrap')}>
                                            <Link
                                                href={saldo.TautanKartuStok}
                                                className="font-semibold text-brand underline"
                                                aria-label={`Kartu stok ${saldo.NamaProduk} di ${saldo.NamaGudang}`}
                                            >
                                                Kartu stok
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </section>
            )}

            <Paginasi
                alamat={alamat}
                saring={BuatQuerySaldo(Saring)}
                halamanSaatIni={Saldo.HalamanSaatIni}
                halamanTerakhir={Saldo.HalamanTerakhir}
                total={Saldo.Total}
                label="Halaman saldo stok"
            />
        </TataLetakAplikasi>
    );
}

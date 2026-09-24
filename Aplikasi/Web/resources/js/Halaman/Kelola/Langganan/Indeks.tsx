import { Link, useForm } from '@inertiajs/react';
import { useId, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { FieldError, FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { RadioGroup, RadioGroupItem } from '@/Komponen/Ui/radio-group';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import { JenisLabelTagihan, type TagihanLangganan } from '@/Tipe/TagihanLangganan';

type RingkasanLangganan = {
    Status: 'Trial' | 'Aktif' | 'Tertunggak' | 'Ditangguhkan' | 'Berhenti' | 'Gratis';
    LabelStatus: string;
    KodePaket: string;
    NamaPaket: string;
    SiklusTagihan: 'Bulanan' | 'Tahunan';
    TrialBerakhirPada: string | null;
    PeriodeMulai: string | null;
    PeriodeSelesai: string | null;
    BatasTenggangPada: string | null;
};

type PilihanPaket = {
    Kode: string;
    Nama: string;
    Keterangan: string | null;
    HargaBulanan: string;
    HargaTahunan: string;
    PaketBerjalan: boolean;
    BisaDipilih: boolean;
};

type PropsLangganan = {
    Langganan: RingkasanLangganan | null;
    PilihanPaket: PilihanPaket[];
    Tagihan: TagihanLangganan[];
    HariMasaTenggang: number;
};

const jenisStatusLangganan = {
    Trial: 'peringatan',
    Aktif: 'sukses',
    Tertunggak: 'bahaya',
    Ditangguhkan: 'bahaya',
    Berhenti: 'netral',
    Gratis: 'netral',
} as const;

/** Langganan & tagihan tenant (P-08/F-19 Fase 0): pilih paket, buat tagihan, bayar lewat transfer manual. */
export default function HalamanLangganan({ Langganan, PilihanPaket, Tagihan, HariMasaTenggang }: PropsLangganan) {
    const tagihanTerbuka = Tagihan.find((tagihan) => tagihan.Status === 'Terbit' || tagihan.Status === 'JatuhTempo');

    return (
        <TataLetakAplikasi judul="Langganan">
            {Langganan === null ? (
                <Pemberitahuan jenis="bahaya" judul="Data langganan tidak ditemukan">
                    Hubungi tim kami agar langganan usaha Anda diperiksa.
                </Pemberitahuan>
            ) : (
                <>
                    <BannerStatus langganan={Langganan} hariMasaTenggang={HariMasaTenggang} />
                    <RingkasanStatus langganan={Langganan} />
                </>
            )}
            {tagihanTerbuka ? (
                <Pemberitahuan jenis="peringatan" judul={`Tagihan ${tagihanTerbuka.Nomor} menunggu pembayaran`}>
                    <p>
                        Total {FormatRupiah(tagihanTerbuka.Total)}, jatuh tempo{' '}
                        {FormatTanggalWaktu(tagihanTerbuka.JatuhTempoPada)}.{' '}
                        <Link
                            href={`/kelola/langganan/tagihan/${tagihanTerbuka.Uuid}`}
                            className="font-semibold text-brand underline"
                        >
                            Bayar tagihan
                        </Link>
                    </p>
                </Pemberitahuan>
            ) : Langganan !== null && Langganan.Status !== 'Berhenti' ? (
                <FormPilihPaket pilihan={PilihanPaket} langganan={Langganan} />
            ) : null}
            <RiwayatTagihan tagihan={Tagihan} />
        </TataLetakAplikasi>
    );
}

function BannerStatus({ langganan, hariMasaTenggang }: { langganan: RingkasanLangganan; hariMasaTenggang: number }) {
    switch (langganan.Status) {
        case 'Trial':
            return (
                <Pemberitahuan jenis="info" judul="Masa trial">
                    Trial berakhir {FormatTanggalWaktu(langganan.TrialBerakhirPada)}. Setelah itu usaha Anda turun ke
                    paket Gratis. Pilih paket di bawah untuk tetap memakai semua fitur.
                </Pemberitahuan>
            );
        case 'Tertunggak':
            return (
                <Pemberitahuan jenis="peringatan" judul="Langganan tertunggak">
                    Periode langganan sudah berakhir. Semua fitur masih berjalan sampai{' '}
                    {FormatTanggalWaktu(langganan.BatasTenggangPada)} (masa tenggang {hariMasaTenggang} hari). Bayar
                    perpanjangan agar kasir tidak terkunci.
                </Pemberitahuan>
            );
        case 'Ditangguhkan':
            return (
                <Pemberitahuan jenis="bahaya" judul="Langganan ditangguhkan">
                    Kasir terkunci. Anda tetap bisa masuk, melihat laporan, mengekspor data, dan membayar tagihan.
                </Pemberitahuan>
            );
        default:
            return null;
    }
}

function RingkasanStatus({ langganan }: { langganan: RingkasanLangganan }) {
    return (
        <Card aria-labelledby="judul-status" role="region" className="px-4 py-3">
            <h2 id="judul-status" className="sr-only">
                Status langganan
            </h2>
            <dl className="grid gap-x-6 gap-y-2 text-isi sm:grid-cols-4">
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Paket</dt>
                    <dd className="font-semibold text-teks-utama">{langganan.NamaPaket}</dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Status</dt>
                    <dd>
                        <LabelStatus jenis={jenisStatusLangganan[langganan.Status]} teks={langganan.LabelStatus} />
                    </dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Siklus tagihan</dt>
                    <dd>
                        {langganan.Status === 'Aktif' || langganan.Status === 'Tertunggak'
                            ? langganan.SiklusTagihan
                            : '—'}
                    </dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Berlaku sampai</dt>
                    <dd>
                        {langganan.Status === 'Trial'
                            ? FormatTanggalWaktu(langganan.TrialBerakhirPada)
                            : FormatTanggalWaktu(langganan.PeriodeSelesai)}
                    </dd>
                </div>
            </dl>
        </Card>
    );
}

function FormPilihPaket({ pilihan, langganan }: { pilihan: PilihanPaket[]; langganan: RingkasanLangganan }) {
    const idLegenda = useId();
    const bisaDipilih = pilihan.filter((paket) => paket.BisaDipilih);
    const bawaan =
        bisaDipilih.find((paket) => paket.PaketBerjalan) ??
        bisaDipilih.find((paket) => paket.Kode === langganan.KodePaket) ??
        bisaDipilih[0];
    const formulir = useForm<{ KodePaket: string; Siklus: string; KodeKupon: string }>({
        KodePaket: bawaan?.Kode ?? '',
        Siklus: langganan.SiklusTagihan,
        KodeKupon: '',
    });
    const perpanjangan = langganan.Status === 'Aktif' || langganan.Status === 'Tertunggak';

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/kelola/langganan/tagihan', { preserveScroll: true });
    };

    if (pilihan.length === 0) {
        return (
            <Pemberitahuan jenis="info" judul="Belum ada paket yang bisa dibeli">
                Harga paket sedang disiapkan. Coba lagi nanti atau hubungi tim kami.
            </Pemberitahuan>
        );
    }

    return (
        <Card className="p-4">
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <h2 className="text-subjudul font-semibold text-teks-utama">
                    {perpanjangan ? 'Perpanjang langganan' : 'Pilih paket berbayar'}
                </h2>
                <FieldSet className="gap-2">
                    <FieldLegend id={idLegenda} className="mb-0 text-label font-semibold text-teks-utama">
                        Paket
                    </FieldLegend>
                    <RadioGroup
                        name="KodePaket"
                        value={formulir.data.KodePaket}
                        onValueChange={(nilai) => formulir.setData('KodePaket', nilai)}
                        aria-labelledby={idLegenda}
                        aria-invalid={formulir.errors.KodePaket ? true : undefined}
                        className="block"
                    >
                        <Table className="text-isi">
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col" className="px-3">
                                        Paket
                                    </TableHead>
                                    <TableHead scope="col" className="px-3 text-right">
                                        Per bulan
                                    </TableHead>
                                    <TableHead scope="col" className="px-3 text-right">
                                        Per tahun
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pilihan.map((paket) => (
                                    <TableRow
                                        key={paket.Kode}
                                        data-state={formulir.data.KodePaket === paket.Kode ? 'selected' : undefined}
                                    >
                                        <TableCell className="px-3 whitespace-normal">
                                            <div className="flex items-start gap-2">
                                                <RadioGroupItem
                                                    id={`${idLegenda}-${paket.Kode}`}
                                                    value={paket.Kode}
                                                    disabled={!paket.BisaDipilih}
                                                    className="mt-1"
                                                />
                                                <label
                                                    htmlFor={`${idLegenda}-${paket.Kode}`}
                                                    className={paket.BisaDipilih ? 'cursor-pointer' : undefined}
                                                >
                                                    <span className="font-semibold text-teks-utama">{paket.Nama}</span>
                                                    {paket.PaketBerjalan ? (
                                                        <span className="block text-keterangan text-teks-sekunder">
                                                            Paket Anda saat ini
                                                        </span>
                                                    ) : null}
                                                    {!paket.BisaDipilih ? (
                                                        <span className="block text-keterangan text-teks-sekunder">
                                                            Ganti paket saat langganan aktif belum tersedia. Hubungi tim
                                                            kami.
                                                        </span>
                                                    ) : null}
                                                </label>
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-3 text-right tabular-nums">
                                            {FormatRupiah(paket.HargaBulanan)}
                                        </TableCell>
                                        <TableCell className="px-3 text-right tabular-nums">
                                            {FormatRupiah(paket.HargaTahunan)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </RadioGroup>
                    {formulir.errors.KodePaket ? (
                        <FieldError className="text-keterangan font-semibold">{formulir.errors.KodePaket}</FieldError>
                    ) : null}
                </FieldSet>
                <div className="grid gap-4 sm:grid-cols-2">
                    <BidangPilihan
                        label="Siklus tagihan"
                        nilai={formulir.data.Siklus}
                        opsi={[
                            { Nilai: 'Bulanan', Label: 'Bulanan' },
                            { Nilai: 'Tahunan', Label: 'Tahunan (lebih hemat)' },
                        ]}
                        saatBerubah={(nilai) => formulir.setData('Siklus', nilai)}
                        galat={formulir.errors.Siklus}
                    />
                    <BidangTeks
                        label="Kode kupon (opsional)"
                        kode
                        maxLength={30}
                        nilai={formulir.data.KodeKupon}
                        saatBerubah={(nilai) => formulir.setData('KodeKupon', nilai.toUpperCase())}
                        galat={formulir.errors.KodeKupon}
                    />
                </div>
                <p className="text-keterangan text-teks-sekunder">
                    Harga belum termasuk PPN. Rincian PPN dan total tampil di tagihan sebelum Anda membayar.
                </p>
                <div>
                    <Tombol type="submit" memproses={formulir.processing} disabled={formulir.data.KodePaket === ''}>
                        Buat tagihan
                    </Tombol>
                </div>
            </form>
        </Card>
    );
}

function RiwayatTagihan({ tagihan }: { tagihan: TagihanLangganan[] }) {
    return (
        <section aria-labelledby="judul-riwayat" className="flex flex-col gap-2">
            <h2 id="judul-riwayat" className="text-subjudul font-semibold text-teks-utama">
                Riwayat tagihan
            </h2>
            {tagihan.length === 0 ? (
                <Empty className="items-start border border-solid border-garis bg-permukaan p-6 text-left md:p-6">
                    <EmptyHeader className="max-w-none items-start text-left">
                        <EmptyDescription className="text-isi text-teks-sekunder">Belum ada tagihan.</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="text-isi">
                        <TableCaption className="sr-only">Riwayat tagihan langganan</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col" className="px-4">
                                    Nomor
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Paket
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Jatuh tempo
                                </TableHead>
                                <TableHead scope="col" className="px-4 text-right">
                                    Total
                                </TableHead>
                                <TableHead scope="col" className="px-4">
                                    Status
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tagihan.map((baris) => (
                                <TableRow key={baris.Uuid}>
                                    <TableCell className="px-4 font-mono text-label">
                                        <Link
                                            href={`/kelola/langganan/tagihan/${baris.Uuid}`}
                                            className="text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            {baris.Nomor}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="px-4">
                                        {baris.NamaPaket} · {baris.Siklus}
                                    </TableCell>
                                    <TableCell className="px-4">{FormatTanggalWaktu(baris.JatuhTempoPada)}</TableCell>
                                    <TableCell className="px-4 text-right tabular-nums">
                                        {FormatRupiah(baris.Total)}
                                    </TableCell>
                                    <TableCell className="px-4">
                                        <LabelStatus jenis={JenisLabelTagihan(baris.Status)} teks={baris.LabelStatus} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
        </section>
    );
}

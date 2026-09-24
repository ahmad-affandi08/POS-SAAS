import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { BuatKelasKontrol, GalatBidang, KerangkaBidang, LabelBidang } from '@/Komponen/Formulir/BagianBidang';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import KerangkaMemuat from '@/Komponen/Persediaan/KerangkaMemuat';
import PemilihProdukStok from '@/Komponen/Persediaan/PemilihProdukStok';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Input } from '@/Komponen/Ui/input';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import {
    AmbilLabelPelacakan,
    FormatHppSatuan,
    FormatJumlahStok,
    FormatLabelGudang,
    FormatNilai,
} from '@/Pustaka/FormatPersediaan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsKartuStok } from '@/Tipe/Persediaan';

const alamat = '/kelola/persediaan/kartu-stok';

type SaringKartu = PropsKartuStok['Saring'];
type ProdukKartu = { Uuid: string; Nama: string; Sku: string | null };

/** Parameter query kartu stok (DesainF05a D: `?produk=&gudang=&dari=&sampai=&halaman=`). */
export function BuatQueryKartuStok(saring: SaringKartu): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.UuidProduk) {
        query.produk = saring.UuidProduk;
    }

    if (saring.UuidGudang) {
        query.gudang = saring.UuidGudang;
    }

    if (saring.Dari) {
        query.dari = saring.Dari;
    }

    if (saring.Sampai) {
        query.sampai = saring.Sampai;
    }

    return query;
}

/** Galat lokal saringan (server tetap memeriksa ulang). */
export function PeriksaSaringKartu(saring: SaringKartu): Partial<Record<'Produk' | 'Gudang' | 'Sampai', string>> {
    const galat: Partial<Record<'Produk' | 'Gudang' | 'Sampai', string>> = {};

    if (!saring.UuidProduk) {
        galat.Produk = 'Pilih produk.';
    }

    if (!saring.UuidGudang) {
        galat.Gudang = 'Pilih lokasi stok.';
    }

    if (saring.Dari && saring.Sampai && saring.Dari > saring.Sampai) {
        galat.Sampai = 'Tanggal akhir tidak boleh sebelum tanggal awal.';
    }

    return galat;
}

function BidangTanggalSaring({
    id,
    label,
    nilai,
    saatBerubah,
    galat,
}: {
    id: string;
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
}) {
    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <Input
                id={id}
                type="date"
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={galat ? `${id}-galat` : undefined}
                className={BuatKelasKontrol(galat, 'tabular-nums')}
            />
            {galat ? <GalatBidang id={`${id}-galat`}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

/** F-05a: kartu stok satu produk di satu lokasi, urut pencatatan (H-5), dengan saldo awal, berjalan, dan akhir. */
export default function HalamanKartuStok({
    Produk,
    Gudang,
    Saring,
    SaldoAwal,
    SaldoAkhir,
    Mutasi,
    OpsiGudang,
}: PropsKartuStok) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [produk, AturProduk] = useState<ProdukKartu | null>(Produk);
    const [saring, AturSaring] = useState<SaringKartu>(Saring);
    const [periksa, AturPeriksa] = useState(false);
    const [memuat, AturMemuat] = useState(false);
    const galat = periksa ? PeriksaSaringKartu(saring) : {};
    const simbol = Produk?.SimbolSatuan ?? '';
    const kelasKepala = 'px-2 h-auto text-label font-semibold text-teks-sekunder';
    const kelasAngka = 'px-2 py-2 text-right whitespace-nowrap tabular-nums';
    const opsiGudang =
        OpsiGudang.some((gudang) => gudang.Uuid === Gudang?.Uuid) || Gudang === null
            ? OpsiGudang
            : [...OpsiGudang, Gudang];

    const Tampilkan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (Object.keys(PeriksaSaringKartu(saring)).length > 0) {
            return;
        }

        router.get(alamat, BuatQueryKartuStok(saring), {
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });
    };

    const pelacakan = Produk ? AmbilLabelPelacakan(Produk.Pelacakan) : null;

    return (
        <TataLetakAplikasi judul="Kartu stok">
            <DaftarGalatServer galat={props.errors} />

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Tampilkan}
                    noValidate
                    aria-label="Pilih produk, lokasi, dan rentang tanggal kartu stok"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="sm:col-span-2">
                        {produk ? (
                            <div className="flex flex-col gap-1">
                                <span className="text-label font-semibold text-teks-utama">Produk</span>
                                <div className="flex min-h-10 flex-wrap items-center gap-2">
                                    <span className="font-semibold break-words text-teks-utama">{produk.Nama}</span>
                                    <span className="font-mono text-keterangan text-teks-sekunder">
                                        {produk.Sku ?? 'Tanpa SKU'}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="link"
                                        className="h-auto px-0"
                                        onClick={() => {
                                            AturProduk(null);
                                            AturSaring({ ...saring, UuidProduk: null });
                                        }}
                                    >
                                        Ganti produk
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <PemilihProdukStok
                                label="Produk"
                                uuidGudang={saring.UuidGudang}
                                saatPilih={(pilihan) => {
                                    AturProduk({ Uuid: pilihan.Uuid, Nama: pilihan.Nama, Sku: pilihan.Sku });
                                    AturSaring({ ...saring, UuidProduk: pilihan.Uuid });
                                }}
                                galat={galat.Produk}
                            />
                        )}
                    </div>
                    <div className="sm:col-span-2">
                        <BidangPilihan
                            label="Lokasi stok"
                            nilai={saring.UuidGudang ?? ''}
                            kosong="Pilih lokasi stok"
                            opsi={opsiGudang.map((gudang) => ({
                                Nilai: gudang.Uuid,
                                Label: FormatLabelGudang(gudang),
                            }))}
                            saatBerubah={(nilai) => AturSaring({ ...saring, UuidGudang: nilai === '' ? null : nilai })}
                            galat={galat.Gudang}
                        />
                    </div>
                    <BidangTanggalSaring
                        id="kartu-stok-dari"
                        label="Dari tanggal"
                        nilai={saring.Dari}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Dari: nilai })}
                    />
                    <BidangTanggalSaring
                        id="kartu-stok-sampai"
                        label="Sampai tanggal"
                        nilai={saring.Sampai}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Sampai: nilai })}
                        galat={galat.Sampai}
                    />
                    <div className="flex items-end sm:col-span-2">
                        <Tombol type="submit" memproses={memuat}>
                            Tampilkan kartu stok
                        </Tombol>
                    </div>
                </form>
            </Card>

            {memuat ? (
                <KerangkaMemuat label="Memuat kartu stok…" />
            ) : Produk === null || Gudang === null || Mutasi === null ? (
                <KeadaanKosong judul="Pilih produk dan lokasi stok, lalu tekan Tampilkan kartu stok.">
                    <span>
                        Kartu stok berisi setiap barang masuk dan keluar beserta saldo berjalannya. Bisa juga dibuka
                        dari{' '}
                        <Link href="/kelola/persediaan/saldo" className="font-semibold text-brand underline">
                            Saldo stok
                        </Link>
                        .
                    </span>
                </KeadaanKosong>
            ) : (
                <PanelKatalog
                    judul={`${Produk.Nama} di ${Gudang.Nama}`}
                    idJudul="judul-kartu-stok"
                    keterangan={
                        <>
                            <span className="font-mono">{Produk.Sku ?? 'Tanpa SKU'}</span>
                            {pelacakan ? ` · ${pelacakan}` : ''} · {FormatLabelGudang(Gudang)} ·{' '}
                            {Saring.Dari ? FormatTanggal(Saring.Dari) : 'awal pencatatan'} sampai{' '}
                            {Saring.Sampai ? FormatTanggal(Saring.Sampai) : 'hari ini'} · urut sesuai waktu pencatatan
                        </>
                    }
                >
                    {Mutasi.Data.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">Tidak ada mutasi stok di rentang tanggal ini.</p>
                    ) : null}
                    <Table className="min-w-[1100px] text-left text-isi">
                        <TableCaption className="sr-only">
                            Kartu stok {Produk.Nama}, {Mutasi.Total} mutasi
                        </TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Tanggal
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Jenis
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Referensi
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Masuk
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Keluar
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    HPP satuan
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Nilai mutasi
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Saldo
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Nilai saldo
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Batch / seri
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Dicatat oleh
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {SaldoAwal ? (
                                <TableRow className="border-garis bg-permukaan-redup hover:bg-permukaan-redup">
                                    <th scope="row" colSpan={7} className="px-2 py-2 text-left font-semibold">
                                        Saldo awal
                                    </th>
                                    <TableCell className={cn(kelasAngka, 'font-semibold')}>
                                        {FormatJumlahStok(SaldoAwal.Jumlah, simbol)}
                                    </TableCell>
                                    <TableCell className={cn(kelasAngka, 'font-semibold')}>
                                        {FormatNilai(SaldoAwal.Nilai)}
                                    </TableCell>
                                    <TableCell colSpan={2} />
                                </TableRow>
                            ) : null}
                            {Mutasi.Data.map((mutasi, indeks) => (
                                <TableRow
                                    key={`${mutasi.DicatatPada}-${String(indeks)}`}
                                    className="border-garis align-top"
                                >
                                    <TableCell className="px-2 py-2 whitespace-nowrap">
                                        {FormatTanggal(mutasi.TanggalBisnis)}
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {FormatTanggalWaktu(mutasi.DicatatPada)}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-2 py-2 whitespace-normal">
                                        {mutasi.LabelJenisMutasi}
                                    </TableCell>
                                    <TableCell className="px-2 py-2 whitespace-normal">
                                        {mutasi.TautanReferensi && mutasi.NomorReferensi ? (
                                            <Link
                                                href={mutasi.TautanReferensi}
                                                className="font-mono font-semibold break-all text-brand underline"
                                            >
                                                {mutasi.NomorReferensi}
                                            </Link>
                                        ) : (
                                            <span className="font-mono break-all">{mutasi.NomorReferensi ?? '—'}</span>
                                        )}
                                    </TableCell>
                                    <TableCell className={kelasAngka}>
                                        {mutasi.Masuk === null ? '' : FormatJumlahStok(mutasi.Masuk)}
                                    </TableCell>
                                    <TableCell className={kelasAngka}>
                                        {mutasi.Keluar === null ? '' : FormatJumlahStok(mutasi.Keluar)}
                                    </TableCell>
                                    <TableCell className={kelasAngka}>{FormatHppSatuan(mutasi.HppSatuan)}</TableCell>
                                    <TableCell className={kelasAngka}>{FormatNilai(mutasi.TotalHpp)}</TableCell>
                                    <TableCell className={kelasAngka}>
                                        {FormatJumlahStok(mutasi.SaldoSetelah)}
                                    </TableCell>
                                    <TableCell className={kelasAngka}>{FormatNilai(mutasi.NilaiSetelah)}</TableCell>
                                    <TableCell className="px-2 py-2 font-mono text-keterangan break-all whitespace-normal">
                                        {mutasi.NomorBatch ?? mutasi.NomorSeri ?? '—'}
                                    </TableCell>
                                    <TableCell className="px-2 py-2 whitespace-normal text-teks-sekunder">
                                        {mutasi.DicatatOleh ?? 'Sistem'}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {SaldoAkhir ? (
                                <TableRow className="border-garis bg-permukaan-redup hover:bg-permukaan-redup">
                                    <th scope="row" colSpan={7} className="px-2 py-2 text-left font-semibold">
                                        Saldo akhir
                                    </th>
                                    <TableCell className={cn(kelasAngka, 'font-semibold')}>
                                        {FormatJumlahStok(SaldoAkhir.Jumlah, simbol)}
                                    </TableCell>
                                    <TableCell className={cn(kelasAngka, 'font-semibold')}>
                                        {FormatNilai(SaldoAkhir.Nilai)}
                                    </TableCell>
                                    <TableCell colSpan={2} />
                                </TableRow>
                            ) : null}
                        </TableBody>
                    </Table>
                </PanelKatalog>
            )}

            {Mutasi ? (
                <Paginasi
                    alamat={alamat}
                    saring={BuatQueryKartuStok(Saring)}
                    halamanSaatIni={Mutasi.HalamanSaatIni}
                    halamanTerakhir={Mutasi.HalamanTerakhir}
                    total={Mutasi.Total}
                    label="Halaman kartu stok"
                />
            ) : null}
        </TataLetakAplikasi>
    );
}

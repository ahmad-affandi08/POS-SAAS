import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { EllipsisIcon } from 'lucide-react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import SakelarPadat, { KelasSel, usePadatTabel } from '@/Komponen/Katalog/SakelarPadat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Komponen/Ui/dropdown-menu';
import { Skeleton } from '@/Komponen/Ui/skeleton';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisProduk, JenisProduk, PropsDaftarProduk, SaringProduk } from '@/Tipe/Katalog';
import { CekBatasPenuh, FormatBatas } from '@/Tipe/Organisasi';

const opsiStatus = [
    { Nilai: 'Aktif', Label: 'Aktif' },
    { Nilai: 'Diarsipkan', Label: 'Diarsipkan' },
    { Nilai: 'Semua', Label: 'Semua status' },
];

const opsiUrut = [
    { Nilai: 'Nama', Label: 'Nama (A–Z)' },
    { Nilai: '-DiubahPada', Label: 'Terakhir diubah' },
    { Nilai: 'Sku', Label: 'SKU' },
];

/** Parameter query daftar produk (DesainF03 E.2); isian kosong dan nilai bawaan tidak dikirim. */
export function BuatQueryProduk(saring: SaringProduk): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.Kata.trim() !== '') {
        query.kata = saring.Kata.trim();
    }

    if (saring.Kategori) {
        query['saring[Kategori]'] = saring.Kategori;
    }

    if (saring.Jenis) {
        query['saring[Jenis]'] = saring.Jenis;
    }

    if (saring.Status !== 'Aktif') {
        query['saring[Status]'] = saring.Status;
    }

    if (saring.Urut !== 'Nama') {
        query.urut = saring.Urut;
    }

    return query;
}

/** Tautan ekspor Excel dengan saringan yang sedang aktif. */
export function BuatUrlEksporProduk(saring: SaringProduk): string {
    return `/kelola/produk/ekspor?${new URLSearchParams({ format: 'xlsx', ...BuatQueryProduk(saring) }).toString()}`;
}

/** Menu aksi satu baris produk (DropdownMenu): lihat, ubah, arsipkan/pulihkan. */
function AksiBarisProduk({
    produk,
    saatUbahStatus,
}: {
    produk: BarisProduk;
    saatUbahStatus: (produk: BarisProduk, aksi: 'arsipkan' | 'pulihkan') => void;
}) {
    const aktif = produk.Status === 'Aktif';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={`Aksi untuk ${produk.Nama}`}>
                    <EllipsisIcon aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link href={`/kelola/produk/${produk.Uuid}`}>Lihat detail</Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={`/kelola/produk/${produk.Uuid}/ubah`}>Ubah produk</Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => saatUbahStatus(produk, aktif ? 'arsipkan' : 'pulihkan')}>
                    {aktif ? 'Arsipkan' : 'Pulihkan'}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** F-03: daftar produk dengan pencarian, saringan, arsip/pulihkan, ekspor, dan mode tabel padat. */
export default function HalamanDaftarProduk({ Produk, Saring, Kategori, Jenis, BatasSku, Izin }: PropsDaftarProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [saring, AturSaring] = useState<SaringProduk>(Saring);
    const [memuat, AturMemuat] = useState(false);
    const [padat, AturPadat] = usePadatTabel('Produk');
    const penuh = CekBatasPenuh(BatasSku);
    const adaSaringan = Object.keys(BuatQueryProduk({ ...Saring, Urut: 'Nama' })).length > 0;
    const sel = KelasSel(padat);
    const kelasKepala = cn(sel, 'h-auto text-label font-semibold text-teks-sekunder');

    const Terapkan = (baru: SaringProduk) =>
        router.get('/kelola/produk', BuatQueryProduk(baru), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        Terapkan(saring);
    };

    const Ubah = <K extends keyof SaringProduk>(kunci: K, nilai: SaringProduk[K]) => {
        const baru = { ...saring, [kunci]: nilai };
        AturSaring(baru);

        if (kunci !== 'Kata') {
            Terapkan(baru);
        }
    };

    const UbahStatus = (produk: BarisProduk, aksi: 'arsipkan' | 'pulihkan') =>
        router.post(`/kelola/produk/${produk.Uuid}/${aksi}`, {}, { preserveScroll: true });

    return (
        <TataLetakAplikasi judul="Produk">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Produk terhitung paket:{' '}
                    <span className="font-semibold text-teks-utama tabular-nums">
                        {FormatBatas(BatasSku, 'produk')}
                    </span>
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" className="h-10">
                        <a href={BuatUrlEksporProduk(Saring)}>Ekspor ke Excel</a>
                    </Button>
                    {Izin.Kelola ? (
                        <>
                            <Button asChild variant="outline" className="h-10">
                                <Link href="/kelola/produk/impor">Impor dari Excel</Link>
                            </Button>
                            {penuh ? (
                                <Button disabled className="h-10">
                                    Tambah produk
                                </Button>
                            ) : (
                                <Button asChild className="h-10">
                                    <Link href="/kelola/produk/buat">Tambah produk</Link>
                                </Button>
                            )}
                        </>
                    ) : null}
                </div>
            </div>

            {Izin.Kelola && penuh ? (
                <Pemberitahuan jenis="info" judul="Batas produk paket sudah tercapai">
                    Arsipkan produk yang tidak dijual lagi, atau tingkatkan paket di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    . Produk diarsipkan dan induk varian tidak dihitung.
                </Pemberitahuan>
            ) : null}
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="produk" /> : null}
            <DaftarGalatServer galat={props.errors} />

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Kirim}
                    role="search"
                    aria-label="Cari dan saring produk"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div className="sm:col-span-2 lg:col-span-2">
                        <BidangTeks
                            label="Cari produk"
                            nilai={saring.Kata}
                            saatBerubah={(nilai) => Ubah('Kata', nilai)}
                            keterangan="Nama, SKU, atau barcode lengkap. Tekan Enter untuk mencari."
                            maxLength={100}
                        />
                    </div>
                    <BidangPilihan
                        label="Kategori"
                        nilai={saring.Kategori ?? ''}
                        kosong="Semua kategori"
                        opsi={Kategori.map((kategori) => ({ Nilai: kategori.Uuid, Label: kategori.Jalur }))}
                        saatBerubah={(nilai) => Ubah('Kategori', nilai === '' ? null : nilai)}
                    />
                    <BidangPilihan
                        label="Jenis"
                        nilai={saring.Jenis ?? ''}
                        kosong="Semua jenis"
                        opsi={Jenis.map((jenis) => ({ Nilai: jenis.Nilai, Label: jenis.Label }))}
                        saatBerubah={(nilai) => Ubah('Jenis', nilai === '' ? null : (nilai as JenisProduk))}
                    />
                    <BidangPilihan
                        label="Status"
                        nilai={saring.Status}
                        opsi={opsiStatus}
                        saatBerubah={(nilai) => Ubah('Status', nilai as SaringProduk['Status'])}
                    />
                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
                        <div className="w-full sm:w-56">
                            <BidangPilihan
                                label="Urutkan"
                                nilai={saring.Urut}
                                opsi={opsiUrut}
                                saatBerubah={(nilai) => Ubah('Urut', nilai as SaringProduk['Urut'])}
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
                {memuat ? 'Memuat produk…' : `${String(Produk.Total)} produk ditemukan.`}
            </div>

            {memuat ? (
                <div aria-hidden="true" className="flex flex-col gap-2 rounded-panel border border-garis bg-card p-4">
                    {[0, 1, 2, 3, 4].map((baris) => (
                        <Skeleton key={baris} className="h-8 rounded-kontrol" />
                    ))}
                </div>
            ) : Produk.Data.length === 0 ? (
                adaSaringan ? (
                    <KeadaanKosong judul="Tidak ada produk yang cocok dengan pencarian atau saringan ini.">
                        <Button asChild variant="link" className="h-auto px-0">
                            <Link href="/kelola/produk">Hapus saringan</Link>
                        </Button>
                    </KeadaanKosong>
                ) : (
                    <KeadaanKosong judul="Belum ada produk. Impor dari Excel atau Tambah produk">
                        {Izin.Kelola ? (
                            <>
                                <Button asChild variant="outline">
                                    <Link href="/kelola/produk/impor">Impor dari Excel</Link>
                                </Button>
                                <Button asChild>
                                    <Link href="/kelola/produk/buat">Tambah produk</Link>
                                </Button>
                            </>
                        ) : (
                            <span>Minta pengelola produk menambahkan produk.</span>
                        )}
                    </KeadaanKosong>
                )
            ) : (
                <section className="rounded-panel border border-garis bg-card">
                    <Table className={cn('min-w-[760px] text-left', padat ? 'text-label' : 'text-isi')}>
                        <TableCaption className="sr-only">Daftar produk, {Produk.Total} produk</TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Produk
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    SKU
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Jenis
                                </TableHead>
                                <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                                    Harga dasar
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Kasir
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Status
                                </TableHead>
                                {Izin.Kelola ? (
                                    <TableHead scope="col" className={kelasKepala}>
                                        <span className="sr-only">Aksi</span>
                                    </TableHead>
                                ) : null}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Produk.Data.map((produk) => (
                                <TableRow key={produk.Uuid} className="border-garis align-top">
                                    <TableCell className={cn(sel, 'whitespace-normal')}>
                                        <div className="flex items-start gap-2">
                                            {!padat ? (
                                                <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-kontrol border border-garis bg-muted">
                                                    {produk.UrlGambarKecil ? (
                                                        <img
                                                            src={produk.UrlGambarKecil}
                                                            alt=""
                                                            loading="lazy"
                                                            className="max-h-full max-w-full object-cover"
                                                        />
                                                    ) : null}
                                                </div>
                                            ) : null}
                                            <div className="min-w-0">
                                                <Link
                                                    href={`/kelola/produk/${produk.Uuid}`}
                                                    className="font-semibold break-words text-brand underline"
                                                >
                                                    {produk.Nama}
                                                </Link>
                                                <span className="block text-keterangan text-teks-sekunder">
                                                    {[
                                                        produk.NamaKategori ?? 'Tanpa kategori',
                                                        produk.Merek,
                                                        produk.JumlahVarian > 0
                                                            ? `${String(produk.JumlahVarian)} varian`
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        className={cn(sel, 'font-mono text-label break-all whitespace-normal text-teks-utama')}
                                    >
                                        {produk.Sku ?? '—'}
                                    </TableCell>
                                    <TableCell className={cn(sel, 'whitespace-normal text-teks-sekunder')}>
                                        {produk.LabelJenis}
                                    </TableCell>
                                    <TableCell className={cn(sel, 'text-right whitespace-nowrap tabular-nums')}>
                                        {produk.HargaDasar === null ? (
                                            <span className="text-teks-sekunder">Belum ada harga</span>
                                        ) : (
                                            <>
                                                {FormatRupiah(produk.HargaDasar)}
                                                <span className="text-teks-sekunder"> / {produk.SimbolSatuan}</span>
                                            </>
                                        )}
                                    </TableCell>
                                    <TableCell className={cn(sel, 'text-teks-sekunder')}>
                                        {produk.TampilDiPos ? 'Tampil' : 'Tersembunyi'}
                                    </TableCell>
                                    <TableCell className={sel}>
                                        {produk.Status === 'Aktif' ? (
                                            <LabelStatus jenis="sukses" teks="Aktif" />
                                        ) : (
                                            <LabelStatus jenis="netral" teks="Diarsipkan" />
                                        )}
                                    </TableCell>
                                    {Izin.Kelola ? (
                                        <TableCell className={cn(sel, 'text-right')}>
                                            <AksiBarisProduk produk={produk} saatUbahStatus={UbahStatus} />
                                        </TableCell>
                                    ) : null}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}

            <Paginasi
                alamat="/kelola/produk"
                saring={BuatQueryProduk(Saring)}
                halamanSaatIni={Produk.HalamanSaatIni}
                halamanTerakhir={Produk.HalamanTerakhir}
                total={Produk.Total}
                label="Halaman daftar produk"
            />
        </TataLetakAplikasi>
    );
}

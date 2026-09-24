import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import SakelarPadat, { KelasSel, usePadatTabel } from '@/Komponen/Katalog/SakelarPadat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
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

/** F-03: daftar produk dengan pencarian, saringan, arsip/pulihkan, ekspor, dan mode tabel padat. */
export default function HalamanDaftarProduk({ Produk, Saring, Kategori, Jenis, BatasSku, Izin }: PropsDaftarProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [saring, AturSaring] = useState<SaringProduk>(Saring);
    const [memuat, AturMemuat] = useState(false);
    const [padat, AturPadat] = usePadatTabel('Produk');
    const penuh = CekBatasPenuh(BatasSku);
    const adaSaringan = Object.keys(BuatQueryProduk({ ...Saring, Urut: 'Nama' })).length > 0;
    const sel = KelasSel(padat);

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
                    <a
                        href={BuatUrlEksporProduk(Saring)}
                        className="inline-flex h-10 items-center rounded-kontrol border border-garis-input bg-permukaan px-4 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        Ekspor ke Excel
                    </a>
                    {Izin.Kelola ? (
                        <>
                            <Link
                                href="/kelola/produk/impor"
                                className="inline-flex h-10 items-center rounded-kontrol border border-garis-input bg-permukaan px-4 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                Impor dari Excel
                            </Link>
                            {penuh ? (
                                <Tombol disabled>Tambah produk</Tombol>
                            ) : (
                                <Link
                                    href="/kelola/produk/buat"
                                    className="inline-flex h-10 items-center rounded-kontrol border border-brand bg-brand px-4 text-label font-semibold text-permukaan outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                                >
                                    Tambah produk
                                </Link>
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

            <form
                onSubmit={Kirim}
                role="search"
                aria-label="Cari dan saring produk"
                className="grid gap-3 rounded-panel border border-garis bg-permukaan p-4 sm:grid-cols-2 lg:grid-cols-5"
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
                    <Tombol type="submit" varian="sekunder">
                        Cari
                    </Tombol>
                    <SakelarPadat padat={padat} saatBerubah={AturPadat} />
                </div>
            </form>

            <div aria-live="polite" className="sr-only">
                {memuat ? 'Memuat produk…' : `${String(Produk.Total)} produk ditemukan.`}
            </div>

            {memuat ? (
                <div
                    aria-hidden="true"
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                >
                    {[0, 1, 2, 3, 4].map((baris) => (
                        <div key={baris} className="h-8 animate-pulse rounded-kontrol bg-latar" />
                    ))}
                </div>
            ) : Produk.Data.length === 0 ? (
                adaSaringan ? (
                    <KeadaanKosong judul="Tidak ada produk yang cocok dengan pencarian atau saringan ini.">
                        <Link href="/kelola/produk" className="font-semibold text-brand underline">
                            Hapus saringan
                        </Link>
                    </KeadaanKosong>
                ) : (
                    <KeadaanKosong judul="Belum ada produk. Impor dari Excel atau Tambah produk">
                        {Izin.Kelola ? (
                            <>
                                <Link href="/kelola/produk/impor" className="font-semibold text-brand underline">
                                    Impor dari Excel
                                </Link>
                                <span aria-hidden="true">·</span>
                                <Link href="/kelola/produk/buat" className="font-semibold text-brand underline">
                                    Tambah produk
                                </Link>
                            </>
                        ) : (
                            <span>Minta pengelola produk menambahkan produk.</span>
                        )}
                    </KeadaanKosong>
                )
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className={`w-full min-w-[760px] text-left ${padat ? 'text-label' : 'text-isi'}`}>
                        <caption className="sr-only">Daftar produk, {Produk.Total} produk</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className={`${sel} font-semibold`}>
                                    Produk
                                </th>
                                <th scope="col" className={`${sel} font-semibold`}>
                                    SKU
                                </th>
                                <th scope="col" className={`${sel} font-semibold`}>
                                    Jenis
                                </th>
                                <th scope="col" className={`${sel} text-right font-semibold`}>
                                    Harga dasar
                                </th>
                                <th scope="col" className={`${sel} font-semibold`}>
                                    Kasir
                                </th>
                                <th scope="col" className={`${sel} font-semibold`}>
                                    Status
                                </th>
                                {Izin.Kelola ? (
                                    <th scope="col" className={`${sel} font-semibold`}>
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody>
                            {Produk.Data.map((produk) => (
                                <tr key={produk.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className={sel}>
                                        <div className="flex items-start gap-2">
                                            {!padat ? (
                                                <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-kontrol border border-garis bg-latar">
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
                                    </td>
                                    <td className={`${sel} font-mono text-label break-all text-teks-utama`}>
                                        {produk.Sku ?? '—'}
                                    </td>
                                    <td className={`${sel} text-teks-sekunder`}>{produk.LabelJenis}</td>
                                    <td className={`${sel} text-right whitespace-nowrap tabular-nums`}>
                                        {produk.HargaDasar === null ? (
                                            <span className="text-teks-sekunder">Belum ada harga</span>
                                        ) : (
                                            <>
                                                {FormatRupiah(produk.HargaDasar)}
                                                <span className="text-teks-sekunder"> / {produk.SimbolSatuan}</span>
                                            </>
                                        )}
                                    </td>
                                    <td className={`${sel} text-teks-sekunder`}>
                                        {produk.TampilDiPos ? 'Tampil' : 'Tersembunyi'}
                                    </td>
                                    <td className={sel}>
                                        {produk.Status === 'Aktif' ? (
                                            <LabelStatus jenis="sukses" teks="Aktif" />
                                        ) : (
                                            <LabelStatus jenis="netral" teks="Diarsipkan" />
                                        )}
                                    </td>
                                    {Izin.Kelola ? (
                                        <td className={`${sel} whitespace-nowrap`}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    UbahStatus(
                                                        produk,
                                                        produk.Status === 'Aktif' ? 'arsipkan' : 'pulihkan',
                                                    )
                                                }
                                                className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                aria-label={`${produk.Status === 'Aktif' ? 'Arsipkan' : 'Pulihkan'} ${produk.Nama}`}
                                            >
                                                {produk.Status === 'Aktif' ? 'Arsipkan' : 'Pulihkan'}
                                            </button>
                                        </td>
                                    ) : null}
                                </tr>
                            ))}
                        </tbody>
                    </table>
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

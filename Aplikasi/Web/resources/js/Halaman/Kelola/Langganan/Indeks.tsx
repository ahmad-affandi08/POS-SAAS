import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
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
        <section aria-labelledby="judul-status" className="rounded-panel border border-garis bg-permukaan px-4 py-3">
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
        </section>
    );
}

function FormPilihPaket({ pilihan, langganan }: { pilihan: PilihanPaket[]; langganan: RingkasanLangganan }) {
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
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">
                {perpanjangan ? 'Perpanjang langganan' : 'Pilih paket berbayar'}
            </h2>
            <fieldset className="overflow-x-auto">
                <legend className="mb-2 text-label font-semibold text-teks-utama">Paket</legend>
                <table className="w-full text-left text-isi">
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="px-3 py-2 font-semibold">
                                Paket
                            </th>
                            <th scope="col" className="px-3 py-2 text-right font-semibold">
                                Per bulan
                            </th>
                            <th scope="col" className="px-3 py-2 text-right font-semibold">
                                Per tahun
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {pilihan.map((paket) => (
                            <tr key={paket.Kode} className="border-b border-garis last:border-b-0">
                                <td className="px-3 py-2">
                                    <label className="flex items-start gap-2">
                                        <input
                                            type="radio"
                                            name="KodePaket"
                                            value={paket.Kode}
                                            checked={formulir.data.KodePaket === paket.Kode}
                                            disabled={!paket.BisaDipilih}
                                            onChange={() => formulir.setData('KodePaket', paket.Kode)}
                                            className="mt-1 h-4 w-4 accent-brand"
                                        />
                                        <span>
                                            <span className="font-semibold text-teks-utama">{paket.Nama}</span>
                                            {paket.PaketBerjalan ? (
                                                <span className="block text-keterangan text-teks-sekunder">
                                                    Paket Anda saat ini
                                                </span>
                                            ) : null}
                                            {!paket.BisaDipilih ? (
                                                <span className="block text-keterangan text-teks-sekunder">
                                                    Ganti paket saat langganan aktif belum tersedia. Hubungi tim kami.
                                                </span>
                                            ) : null}
                                        </span>
                                    </label>
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {FormatRupiah(paket.HargaBulanan)}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {FormatRupiah(paket.HargaTahunan)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {formulir.errors.KodePaket ? (
                    <p className="mt-1 text-keterangan font-semibold text-bahaya">{formulir.errors.KodePaket}</p>
                ) : null}
            </fieldset>
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
    );
}

function RiwayatTagihan({ tagihan }: { tagihan: TagihanLangganan[] }) {
    return (
        <section aria-labelledby="judul-riwayat" className="flex flex-col gap-2">
            <h2 id="judul-riwayat" className="text-subjudul font-semibold text-teks-utama">
                Riwayat tagihan
            </h2>
            {tagihan.length === 0 ? (
                <p className="text-isi text-teks-sekunder">Belum ada tagihan.</p>
            ) : (
                <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Riwayat tagihan langganan</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nomor
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Paket
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Jatuh tempo
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Total
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {tagihan.map((baris) => (
                                <tr key={baris.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label">
                                        <Link
                                            href={`/kelola/langganan/tagihan/${baris.Uuid}`}
                                            className="text-brand underline"
                                        >
                                            {baris.Nomor}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2">
                                        {baris.NamaPaket} · {baris.Siklus}
                                    </td>
                                    <td className="px-4 py-2">{FormatTanggalWaktu(baris.JatuhTempoPada)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{FormatRupiah(baris.Total)}</td>
                                    <td className="px-4 py-2">
                                        <LabelStatus jenis={JenisLabelTagihan(baris.Status)} teks={baris.LabelStatus} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

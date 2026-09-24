import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { JenisBahan } from '@/Komponen/Katalog/BantuanKatalog';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import {
    BandingkanDesimal,
    BulatkanDesimal,
    CekDesimalPositif,
    CekDesimalValid,
    FormatMasukanJumlah,
    HitungJumlahKotor,
} from '@/Pustaka/MasukanJumlah';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsResepProduk } from '@/Tipe/Katalog';

/** Rumus susut yang disetujui lead (DesainF03 H.7, diamandemen). Tampil sebagai teks bantuan. */
export const TeksRumusSusut =
    'Jumlah kotor = jumlah bersih ÷ (1 − susut/100). Susut 0 sampai kurang dari 100 %. Contoh: 150 ml dengan susut 10 % → 166,6667 ml.';

type OpsiSatuanBahan = { Uuid: string; Simbol: string; BolehDesimal: boolean };

type BarisBahan = {
    UuidProdukBahan: string;
    NamaBahan: string;
    Sku: string | null;
    Jumlah: string;
    UuidSatuan: string;
    PersenSusut: string;
    OpsiSatuan: OpsiSatuanBahan[];
};

/** Galat lokal per baris bahan (server tetap memeriksa ulang). */
export function PeriksaBahan(baris: BarisBahan): { Jumlah?: string; PersenSusut?: string } {
    const galat: { Jumlah?: string; PersenSusut?: string } = {};

    if (!CekDesimalPositif(baris.Jumlah)) {
        galat.Jumlah = 'Isi jumlah lebih dari 0.';
    }

    const susut = baris.PersenSusut === '' ? '0' : baris.PersenSusut;

    if (!CekDesimalValid(susut) || BandingkanDesimal(susut, '100') >= 0) {
        galat.PersenSusut = 'Susut harus 0 sampai kurang dari 100 %.';
    }

    return galat;
}

function FormatHpp(nilai: string | null): string {
    return nilai === null ? '—' : FormatRupiah(BulatkanDesimal(nilai, 2));
}

/** F-03 resep/BOM produk: versi tak berubah (BR-03.4), susut, dan HPP resep (BR-03.5). */
export default function HalamanResepProduk({ Kepala, Resep, VersiTerbaru, DaftarVersi, Hpp, Izin }: PropsResepProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const versiLama = Resep !== null && VersiTerbaru !== null && Resep.Versi !== VersiTerbaru;
    const bolehUbah = Izin.Kelola && !versiLama;
    const simbolHasil = Resep?.SimbolSatuanHasil ?? '';
    const [jumlahHasil, AturJumlahHasil] = useState(Resep?.JumlahHasil ?? '1');
    const [catatan, AturCatatan] = useState(Resep?.Catatan ?? '');
    const [bahan, AturBahan] = useState<BarisBahan[]>(
        (Resep?.Bahan ?? []).map((item) => ({
            UuidProdukBahan: item.UuidProdukBahan,
            NamaBahan: item.NamaBahan,
            Sku: item.Sku,
            Jumlah: item.Jumlah,
            UuidSatuan: item.UuidSatuan,
            PersenSusut: item.PersenSusut,
            OpsiSatuan: [{ Uuid: item.UuidSatuan, Simbol: item.SimbolSatuan, BolehDesimal: true }],
        })),
    );
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const Ubah = (indeks: number, perubahan: Partial<BarisBahan>) =>
        AturBahan(bahan.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)));

    const Simpan = () => {
        AturPeriksa(true);

        if (
            bahan.length === 0 ||
            !CekDesimalPositif(jumlahHasil) ||
            bahan.some((item) => Object.keys(PeriksaBahan(item)).length > 0)
        ) {
            return;
        }

        router.post(
            `/kelola/produk/${Kepala.Uuid}/resep`,
            {
                JumlahHasil: jumlahHasil,
                Catatan: catatan,
                Bahan: bahan.map((item) => ({
                    UuidProdukBahan: item.UuidProdukBahan,
                    Jumlah: item.Jumlah,
                    UuidSatuan: item.UuidSatuan,
                    PersenSusut: item.PersenSusut === '' ? '0' : item.PersenSusut,
                })),
            },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <TataLetakAplikasi judul={`Resep ${Kepala.Nama}`}>
            <KepalaProduk kepala={Kepala} tabAktif="Resep" />
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="resep produk ini" /> : null}
            <DaftarGalatServer galat={galat} kecuali={Object.keys(galat).filter((kunci) => /^Bahan\./.test(kunci))} />

            {versiLama ? (
                <Pemberitahuan jenis="info" judul={`Anda melihat versi ${String(Resep.Versi)} (bukan yang terbaru)`}>
                    Versi lama hanya bisa dibaca. Transaksi lama tetap memakai versi yang berlaku saat itu.{' '}
                    <Link href={`/kelola/produk/${Kepala.Uuid}/resep`} className="font-semibold text-brand underline">
                        Lihat versi terbaru ({VersiTerbaru})
                    </Link>
                </Pemberitahuan>
            ) : null}

            {Resep === null && !Izin.Kelola ? (
                <KeadaanKosong judul="Belum ada resep untuk produk ini." />
            ) : (
                <section
                    aria-labelledby="judul-resep"
                    className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="judul-resep" className="text-subjudul font-semibold text-teks-utama">
                            {Resep === null ? 'Resep baru' : `Resep versi ${String(Resep.Versi)}`}
                        </h2>
                        {Resep !== null ? (
                            <p className="text-keterangan text-teks-sekunder">
                                Dibuat {FormatTanggalWaktu(Resep.DibuatPada)} oleh {Resep.NamaPembuat ?? 'Sistem'}
                            </p>
                        ) : null}
                    </div>
                    {Resep === null ? (
                        <p className="text-isi text-teks-sekunder">
                            Belum ada resep. Tambah bahan agar HPP dihitung dan stok bahan terpotong saat produk
                            terjual.
                        </p>
                    ) : null}
                    <div className="grid gap-3 sm:grid-cols-2">
                        <BidangJumlah
                            label="Jumlah hasil satu resep"
                            nilai={jumlahHasil}
                            saatBerubah={AturJumlahHasil}
                            {...(simbolHasil ? { akhiran: simbolHasil } : {})}
                            keterangan="Misal 1 porsi, atau 20 bila satu adonan menjadi 20 roti."
                            galat={
                                galat.JumlahHasil ??
                                (periksa && !CekDesimalPositif(jumlahHasil)
                                    ? 'Isi jumlah hasil lebih dari 0.'
                                    : undefined)
                            }
                            disabled={!bolehUbah}
                            required
                        />
                        {bolehUbah ? (
                            <BidangTeksPanjang
                                label="Catatan (opsional)"
                                nilai={catatan}
                                saatBerubah={AturCatatan}
                                galat={galat.Catatan}
                                maksimal={500}
                            />
                        ) : (
                            <p className="text-isi text-teks-sekunder">Catatan: {catatan || '—'}</p>
                        )}
                    </div>

                    <p id="rumus-susut" className="text-keterangan text-teks-sekunder">
                        {TeksRumusSusut}
                    </p>

                    {bahan.length === 0 ? (
                        <p className="rounded-kontrol border border-dashed border-garis-input px-3 py-2 text-isi text-teks-sekunder">
                            Belum ada bahan.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-isi">
                                <caption className="sr-only">Bahan resep</caption>
                                <thead className="border-b border-garis text-label text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className="py-2 pr-2 font-semibold">
                                            Bahan
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Jumlah bersih
                                        </th>
                                        <th scope="col" className="px-2 py-2 font-semibold">
                                            Satuan
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Susut
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Jumlah kotor
                                        </th>
                                        <th scope="col" className="py-2 pl-2 font-semibold">
                                            <span className="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bahan.map((item, indeks) => {
                                        const galatLokal = periksa ? PeriksaBahan(item) : {};
                                        const satuan = item.OpsiSatuan.find((opsi) => opsi.Uuid === item.UuidSatuan);
                                        const kotor = HitungJumlahKotor(item.Jumlah || '0', item.PersenSusut || '0');

                                        return (
                                            <tr
                                                key={item.UuidProdukBahan}
                                                className="border-b border-garis align-top last:border-b-0"
                                            >
                                                <th scope="row" className="py-2 pr-2 text-left font-normal">
                                                    <span className="block font-semibold break-words text-teks-utama">
                                                        {item.NamaBahan}
                                                    </span>
                                                    <span className="font-mono text-keterangan text-teks-sekunder">
                                                        {item.Sku ?? 'Tanpa SKU'}
                                                    </span>
                                                </th>
                                                <td className="px-2 py-2">
                                                    <BidangJumlah
                                                        label={`Jumlah bersih ${item.NamaBahan}`}
                                                        labelTersembunyi
                                                        nilai={item.Jumlah}
                                                        saatBerubah={(nilai) => Ubah(indeks, { Jumlah: nilai })}
                                                        desimal={satuan?.BolehDesimal === false ? 0 : 4}
                                                        galat={
                                                            galat[`Bahan.${String(indeks)}.Jumlah`] ?? galatLokal.Jumlah
                                                        }
                                                        disabled={!bolehUbah}
                                                    />
                                                </td>
                                                <td className="px-2 py-2">
                                                    <BidangPilihan
                                                        label={`Satuan ${item.NamaBahan}`}
                                                        nilai={item.UuidSatuan}
                                                        opsi={item.OpsiSatuan.map((opsi) => ({
                                                            Nilai: opsi.Uuid,
                                                            Label: opsi.Simbol,
                                                        }))}
                                                        saatBerubah={(nilai) => Ubah(indeks, { UuidSatuan: nilai })}
                                                        galat={galat[`Bahan.${String(indeks)}.UuidSatuan`]}
                                                    />
                                                </td>
                                                <td className="px-2 py-2">
                                                    <BidangJumlah
                                                        label={`Susut ${item.NamaBahan}`}
                                                        labelTersembunyi
                                                        nilai={item.PersenSusut}
                                                        saatBerubah={(nilai) => Ubah(indeks, { PersenSusut: nilai })}
                                                        desimal={6}
                                                        digitBulat={3}
                                                        akhiran="%"
                                                        galat={
                                                            galat[`Bahan.${String(indeks)}.PersenSusut`] ??
                                                            galatLokal.PersenSusut
                                                        }
                                                        disabled={!bolehUbah}
                                                    />
                                                </td>
                                                <td
                                                    className="px-2 py-2 text-right whitespace-nowrap tabular-nums"
                                                    aria-describedby="rumus-susut"
                                                >
                                                    {kotor === null
                                                        ? '—'
                                                        : `${FormatMasukanJumlah(kotor)} ${satuan?.Simbol ?? ''}`}
                                                </td>
                                                <td className="py-2 pl-2">
                                                    {bolehUbah ? (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                AturBahan(bahan.filter((_, i) => i !== indeks))
                                                            }
                                                            className="h-10 text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                            aria-label={`Hapus bahan ${item.NamaBahan}`}
                                                        >
                                                            Hapus
                                                        </button>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div aria-live="polite">
                        {periksa && bahan.length === 0 ? (
                            <p className="text-keterangan font-semibold text-bahaya">Tambah minimal satu bahan.</p>
                        ) : null}
                    </div>

                    {bolehUbah ? (
                        <>
                            <PemilihProduk
                                label="Tambah bahan"
                                jenis={JenisBahan}
                                kecuali={[Kepala.Uuid, ...bahan.map((item) => item.UuidProdukBahan)]}
                                keterangan="Bahan baku, barang stok, atau barang produksi."
                                saatPilih={(produk) =>
                                    AturBahan([
                                        ...bahan,
                                        {
                                            UuidProdukBahan: produk.Uuid,
                                            NamaBahan: produk.Nama,
                                            Sku: produk.Sku,
                                            Jumlah: '',
                                            UuidSatuan: produk.UuidSatuanDasar,
                                            PersenSusut: '0',
                                            OpsiSatuan: produk.Satuan.map((satuan) => ({
                                                Uuid: satuan.Uuid,
                                                Simbol: satuan.Simbol,
                                                BolehDesimal: satuan.BolehDesimal,
                                            })),
                                        },
                                    ])
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <div>
                                    <Tombol onClick={Simpan} memproses={memproses}>
                                        Simpan sebagai versi baru
                                    </Tombol>
                                </div>
                                <p className="text-keterangan text-teks-sekunder">
                                    Menyimpan membuat versi {String((VersiTerbaru ?? 0) + 1)}. Versi lama tidak berubah
                                    dan tetap dipakai transaksi lama. Tanpa perubahan, versi tidak bertambah.
                                </p>
                            </div>
                        </>
                    ) : null}
                </section>
            )}

            {Hpp.Status !== 'TanpaResep' ? (
                <section
                    aria-labelledby="judul-hpp"
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-hpp" className="text-subjudul font-semibold text-teks-utama">
                        HPP resep (versi terbaru)
                    </h2>
                    {Hpp.Status === 'BelumTersedia' ? (
                        <p className="text-isi text-teks-sekunder">
                            HPP belum tersedia. HPP bahan muncul setelah stok awal diisi.
                        </p>
                    ) : (
                        <p className="text-isi text-teks-utama">
                            HPP per {simbolHasil || 'satuan hasil'}:{' '}
                            <span className="font-semibold tabular-nums">{FormatHpp(Hpp.HppSatuan)}</span>
                        </p>
                    )}
                    {Hpp.Baris.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-left text-label">
                                <caption className="sr-only">Rincian HPP per bahan</caption>
                                <thead className="border-b border-garis text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className="py-2 pr-2 font-semibold">
                                            Bahan
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Jumlah kotor (satuan dasar)
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            HPP per satuan dasar
                                        </th>
                                        <th scope="col" className="py-2 pl-2 text-right font-semibold">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Hpp.Baris.map((item, indeks) => (
                                        <tr
                                            key={`${item.NamaBahan}-${String(indeks)}`}
                                            className="border-b border-garis last:border-b-0"
                                        >
                                            <td className="py-2 pr-2">{item.NamaBahan}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {FormatMasukanJumlah(item.JumlahKotor)}
                                            </td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {FormatHpp(item.HppSatuanBahan)}
                                            </td>
                                            <td className="py-2 pl-2 text-right tabular-nums">
                                                {FormatHpp(item.Subtotal)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : null}
                </section>
            ) : null}

            {DaftarVersi.length > 0 ? (
                <section
                    aria-labelledby="judul-versi"
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-versi" className="text-subjudul font-semibold text-teks-utama">
                        Riwayat versi
                    </h2>
                    <ol className="flex flex-col divide-y divide-garis">
                        {DaftarVersi.map((versi) => (
                            <li
                                key={versi.Versi}
                                className="flex flex-wrap items-center justify-between gap-2 py-2 text-isi"
                            >
                                {Resep?.Versi === versi.Versi ? (
                                    <span className="font-semibold text-teks-utama" aria-current="page">
                                        Versi {versi.Versi} (sedang dilihat)
                                    </span>
                                ) : (
                                    <Link
                                        href={`/kelola/produk/${Kepala.Uuid}/resep?versi=${String(versi.Versi)}`}
                                        className="font-semibold text-brand underline"
                                    >
                                        Versi {versi.Versi}
                                    </Link>
                                )}
                                <span className="text-keterangan text-teks-sekunder">
                                    {FormatTanggalWaktu(versi.DibuatPada)} · {versi.NamaPembuat ?? 'Sistem'}
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>
            ) : null}
        </TataLetakAplikasi>
    );
}

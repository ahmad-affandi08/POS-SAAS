import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { CekBatasPenuh, FormatBatas, type Batas } from '@/Tipe/Organisasi';
import { AlamatPanduan, type ProdukContoh, type PropsProdukPanduan } from '@/Tipe/PanduanAwal';

const maksimalBarisManual = 20;

/** Sisa kuota SKU paket; null = tanpa batas. */
function HitungSisaSku(batas: Batas): number | null {
    return batas.Batas === null ? null : Math.max(0, batas.Batas - batas.Terpakai);
}

/** Langkah 4 F-01: (a) produk contoh dari template, (d) tambah produk cepat. Import Excel ada di F-03. */
export default function HalamanProdukPanduan({
    Progres,
    AdaTemplate,
    ProdukContoh,
    Kategori,
    Produk,
    JumlahProduk,
    BatasSku,
}: PropsProdukPanduan) {
    const kuotaPenuh = CekBatasPenuh(BatasSku);

    return (
        <TataLetakPanduan progres={Progres} langkah="Produk" lanjut="tandai-selesai">
            <p className="text-isi text-teks-sekunder">
                Tambahkan beberapa produk supaya kasir bisa langsung berjualan. Produk lengkap (varian, resep, stok
                awal) bisa diatur nanti di menu Produk. Kuota paket:{' '}
                <span className="font-semibold text-teks-utama">{FormatBatas(BatasSku, 'SKU produk')}</span>.
            </p>

            {kuotaPenuh ? (
                <Pemberitahuan jenis="peringatan" judul="Kuota produk paket sudah penuh">
                    {FormatBatas(BatasSku, 'SKU produk')}. Tambah kuota di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>{' '}
                    untuk menambah produk baru.
                </Pemberitahuan>
            ) : null}

            <BagianProdukContoh
                adaTemplate={AdaTemplate}
                produkContoh={ProdukContoh}
                batasSku={BatasSku}
                kuotaPenuh={kuotaPenuh}
            />
            <FormProdukCepat kategori={Kategori} kuotaPenuh={kuotaPenuh} />
            <TabelProduk produk={Produk} jumlahProduk={JumlahProduk} />
        </TataLetakPanduan>
    );
}

type PilihanContoh = Record<string, { Dipilih: boolean; Harga: string }>;

type PropsBagianProdukContoh = {
    adaTemplate: boolean;
    produkContoh: ProdukContoh[];
    batasSku: Batas;
    kuotaPenuh: boolean;
};

function BagianProdukContoh({ adaTemplate, produkContoh, batasSku, kuotaPenuh }: PropsBagianProdukContoh) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const kotakSemua = useRef<HTMLInputElement>(null);
    const [terkirim, AturTerkirim] = useState<string[]>([]);
    const formulir = useForm<{ ProdukContoh: { Nama: string; Harga: string }[] }>({ ProdukContoh: [] });
    const [pilihan, AturPilihan] = useState<PilihanContoh>(() =>
        Object.fromEntries(produkContoh.map((item) => [item.Nama, { Dipilih: !item.SudahAda, Harga: item.Harga }])),
    );
    const bisaDipilih = produkContoh.filter((item) => !item.SudahAda);
    const terpilih = bisaDipilih
        .filter((item) => pilihan[item.Nama]?.Dipilih)
        .map((item) => ({ Nama: item.Nama, Harga: pilihan[item.Nama]?.Harga ?? item.Harga }));
    const sisaSku = HitungSisaSku(batasSku);
    const melebihiKuota = sisaSku !== null && terpilih.length > sisaSku;
    const semuaDipilih = bisaDipilih.length > 0 && terpilih.length === bisaDipilih.length;
    const galat = formulir.errors as Record<string, string | undefined>;

    useEffect(() => {
        if (kotakSemua.current) {
            kotakSemua.current.indeterminate = terpilih.length > 0 && !semuaDipilih;
        }
    }, [terpilih.length, semuaDipilih]);

    const UbahBaris = (nama: string, ubahan: Partial<{ Dipilih: boolean; Harga: string }>) =>
        AturPilihan((lama) => ({
            ...lama,
            [nama]: { Dipilih: lama[nama]?.Dipilih ?? false, Harga: lama[nama]?.Harga ?? '', ...ubahan },
        }));

    const PilihSemua = (dipilih: boolean) =>
        AturPilihan((lama) =>
            Object.fromEntries(
                Object.entries(lama).map(([nama, baris]) => [
                    nama,
                    bisaDipilih.some((item) => item.Nama === nama) ? { ...baris, Dipilih: dipilih } : baris,
                ]),
            ),
        );

    const AmbilGalatBaris = (nama: string, bidang: 'Nama' | 'Harga'): string | undefined => {
        const indeks = terkirim.indexOf(nama);

        return indeks < 0 ? undefined : galat[`ProdukContoh.${String(indeks)}.${bidang}`];
    };

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturTerkirim(terpilih.map((item) => item.Nama));
        formulir.transform(() => ({ ProdukContoh: terpilih }));
        formulir.post(AlamatPanduan.ProdukContoh, {
            preserveScroll: true,
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    return (
        <section
            aria-labelledby="judul-produk-contoh"
            className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4 sm:p-6"
        >
            <h2 id="judul-produk-contoh" className="text-subjudul font-semibold text-teks-utama">
                Produk contoh dari template
            </h2>
            {!adaTemplate || produkContoh.length === 0 ? (
                <p className="text-isi text-teks-sekunder">
                    Template Anda belum punya contoh produk. Tambahkan produk sendiri di bawah.
                </p>
            ) : (
                <form ref={elemenFormulir} onSubmit={Kirim} className="flex flex-col gap-3" noValidate>
                    <p className="text-isi text-teks-sekunder">
                        Pilih produk yang Anda jual dan sesuaikan harganya. Produk yang namanya sudah ada tidak
                        ditambahkan lagi.
                    </p>
                    <RingkasanGalatFormulir galat={formulir.errors} />
                    {galat.ProdukContoh ? (
                        <p className="text-keterangan font-semibold text-bahaya">{galat.ProdukContoh}</p>
                    ) : null}
                    <div className="max-h-120 overflow-auto rounded-panel border border-garis">
                        <table className="w-full min-w-[640px] text-left text-isi">
                            <caption className="sr-only">Produk contoh dari template</caption>
                            <thead className="sticky top-0 z-10 border-b border-garis bg-permukaan text-label text-teks-sekunder">
                                <tr>
                                    <th scope="col" className="w-12 px-4 py-2 font-semibold">
                                        <label className="flex items-center gap-2">
                                            <input
                                                ref={kotakSemua}
                                                type="checkbox"
                                                className="size-4 accent-brand"
                                                checked={semuaDipilih}
                                                disabled={bisaDipilih.length === 0}
                                                onChange={(peristiwa) => PilihSemua(peristiwa.target.checked)}
                                            />
                                            <span className="sr-only">Pilih semua</span>
                                        </label>
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Nama
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Kategori
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Satuan
                                    </th>
                                    <th scope="col" className="w-48 px-4 py-2 text-right font-semibold">
                                        Harga jual
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {produkContoh.map((item) => {
                                    const baris = pilihan[item.Nama];
                                    const galatNama = AmbilGalatBaris(item.Nama, 'Nama');

                                    return (
                                        <tr key={item.Nama} className="border-b border-garis last:border-b-0">
                                            <td className="px-4 py-2 align-top">
                                                {item.SudahAda ? null : (
                                                    <input
                                                        type="checkbox"
                                                        className="mt-3 size-4 accent-brand"
                                                        checked={baris?.Dipilih ?? false}
                                                        onChange={(peristiwa) =>
                                                            UbahBaris(item.Nama, { Dipilih: peristiwa.target.checked })
                                                        }
                                                        aria-label={`Pilih ${item.Nama}`}
                                                    />
                                                )}
                                            </td>
                                            <td className="px-4 py-2 align-top break-words text-teks-utama">
                                                <span className="block pt-2">{item.Nama}</span>
                                                {item.SudahAda ? <LabelStatus jenis="netral" teks="Sudah ada" /> : null}
                                                {galatNama ? (
                                                    <span className="block text-keterangan font-semibold text-bahaya">
                                                        {galatNama}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-2 pt-4 align-top text-teks-sekunder">
                                                {item.NamaKategori ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 pt-4 align-top font-mono text-label text-teks-sekunder">
                                                {item.KodeSatuan}
                                            </td>
                                            <td className="px-4 py-2 align-top">
                                                {item.SudahAda ? (
                                                    <span className="block pt-2 text-right text-teks-sekunder tabular-nums">
                                                        {FormatRupiah(item.Harga)}
                                                    </span>
                                                ) : (
                                                    <BidangUang
                                                        label={`Harga jual ${item.Nama}`}
                                                        labelTersembunyi
                                                        nilai={baris?.Harga ?? item.Harga}
                                                        saatBerubah={(nilai) => UbahBaris(item.Nama, { Harga: nilai })}
                                                        galat={AmbilGalatBaris(item.Nama, 'Harga')}
                                                        disabled={!baris?.Dipilih}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div aria-live="polite" className="flex flex-col gap-2">
                        <p className="text-label text-teks-sekunder">
                            {String(terpilih.length)} dari {String(bisaDipilih.length)} produk contoh dipilih.
                        </p>
                        {melebihiKuota ? (
                            <p className="text-label font-semibold text-peringatan">
                                Sisa kuota paket {String(sisaSku)} SKU. Kurangi pilihan menjadi paling banyak{' '}
                                {String(sisaSku)} produk.
                            </p>
                        ) : null}
                    </div>
                    <div>
                        <Tombol
                            type="submit"
                            memproses={formulir.processing}
                            disabled={terpilih.length === 0 || melebihiKuota || kuotaPenuh}
                        >
                            Tambahkan produk contoh
                        </Tombol>
                    </div>
                </form>
            )}
        </section>
    );
}

type BarisManual = { Nama: string; Harga: string; Kategori: string };

const barisKosong: BarisManual = { Nama: '', Harga: '', Kategori: '' };

function FormProdukCepat({
    kategori,
    kuotaPenuh,
}: {
    kategori: { Uuid: string; Nama: string }[];
    kuotaPenuh: boolean;
}) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const formulir = useForm<{ Produk: BarisManual[] }>({ Produk: [barisKosong] });
    const galat = formulir.errors as Record<string, string | undefined>;
    const opsiKategori = kategori.map((item) => ({ Nilai: item.Uuid, Label: item.Nama }));

    const UbahBaris = (indeks: number, ubahan: Partial<BarisManual>) =>
        formulir.setData(
            'Produk',
            formulir.data.Produk.map((baris, i) => (i === indeks ? { ...baris, ...ubahan } : baris)),
        );

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((data) => ({
            Produk: data.Produk.map((baris) => ({ ...baris, Kategori: baris.Kategori === '' ? null : baris.Kategori })),
        }));
        formulir.post(AlamatPanduan.Produk, {
            preserveScroll: true,
            onSuccess: () => formulir.reset(),
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    return (
        <section
            aria-labelledby="judul-produk-cepat"
            className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4 sm:p-6"
        >
            <h2 id="judul-produk-cepat" className="text-subjudul font-semibold text-teks-utama">
                Tambah produk sendiri
            </h2>
            <form ref={elemenFormulir} onSubmit={Kirim} className="flex flex-col gap-3" noValidate>
                <fieldset disabled={kuotaPenuh} className="flex flex-col gap-3">
                    <legend className="sr-only">Produk baru</legend>
                    <RingkasanGalatFormulir galat={formulir.errors} />
                    {galat.Produk ? <p className="text-keterangan font-semibold text-bahaya">{galat.Produk}</p> : null}
                    <ol className="flex flex-col gap-3">
                        {formulir.data.Produk.map((baris, indeks) => (
                            <li
                                key={indeks}
                                className="grid grid-cols-1 gap-3 border-b border-garis pb-3 last:border-b-0 md:grid-cols-[2fr_1fr_1fr_auto] md:items-start"
                            >
                                <BidangTeks
                                    label={`Nama produk ${String(indeks + 1)}`}
                                    nilai={baris.Nama}
                                    saatBerubah={(nilai) => UbahBaris(indeks, { Nama: nilai })}
                                    galat={galat[`Produk.${String(indeks)}.Nama`]}
                                    maxLength={150}
                                    required
                                />
                                <BidangUang
                                    label="Harga jual"
                                    nilai={baris.Harga}
                                    saatBerubah={(nilai) => UbahBaris(indeks, { Harga: nilai })}
                                    galat={galat[`Produk.${String(indeks)}.Harga`]}
                                    required
                                />
                                <BidangPilihan
                                    label="Kategori"
                                    nilai={baris.Kategori}
                                    opsi={opsiKategori}
                                    saatBerubah={(nilai) => UbahBaris(indeks, { Kategori: nilai })}
                                    galat={galat[`Produk.${String(indeks)}.Kategori`]}
                                    kosong="Tanpa kategori"
                                />
                                <div className="md:pt-6">
                                    {formulir.data.Produk.length > 1 ? (
                                        <Tombol
                                            varian="sekunder"
                                            onClick={() =>
                                                formulir.setData(
                                                    'Produk',
                                                    formulir.data.Produk.filter((_, i) => i !== indeks),
                                                )
                                            }
                                            aria-label={`Hapus baris produk ${String(indeks + 1)}`}
                                        >
                                            Hapus baris
                                        </Tombol>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ol>
                    <div className="flex flex-wrap gap-2">
                        <Tombol type="submit" memproses={formulir.processing}>
                            Tambah produk
                        </Tombol>
                        <Tombol
                            varian="sekunder"
                            onClick={() => formulir.setData('Produk', [...formulir.data.Produk, barisKosong])}
                            disabled={formulir.data.Produk.length >= maksimalBarisManual}
                        >
                            Tambah baris
                        </Tombol>
                    </div>
                    <p className="text-keterangan text-teks-sekunder">
                        Maksimal {maksimalBarisManual} produk sekali simpan. Nama yang sudah ada dilewati.
                    </p>
                </fieldset>
            </form>
        </section>
    );
}

function TabelProduk({ produk, jumlahProduk }: { produk: PropsProdukPanduan['Produk']; jumlahProduk: number }) {
    return (
        <section aria-labelledby="judul-daftar-produk" className="flex flex-col gap-2">
            <h2 id="judul-daftar-produk" className="text-subjudul font-semibold text-teks-utama">
                Produk Anda
            </h2>
            {produk.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    Belum ada produk. Tambah produk pertama Anda.
                </p>
            ) : (
                <>
                    <p className="text-label text-teks-sekunder">
                        {jumlahProduk > produk.length
                            ? `Menampilkan ${String(produk.length)} produk terbaru dari ${String(jumlahProduk)}.`
                            : `${String(jumlahProduk)} produk.`}
                    </p>
                    <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                        <table className="w-full min-w-[480px] text-left text-isi">
                            <caption className="sr-only">Produk terbaru</caption>
                            <thead className="border-b border-garis text-label text-teks-sekunder">
                                <tr>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Nama
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Kategori
                                    </th>
                                    <th scope="col" className="px-4 py-2 text-right font-semibold">
                                        Harga jual
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {produk.map((baris) => (
                                    <tr key={baris.Uuid} className="border-b border-garis last:border-b-0">
                                        <td className="px-4 py-2 break-words text-teks-utama">{baris.Nama}</td>
                                        <td className="px-4 py-2 text-teks-sekunder">{baris.NamaKategori ?? '—'}</td>
                                        <td className="px-4 py-2 text-right text-teks-utama tabular-nums">
                                            {FormatRupiah(baris.Harga)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </section>
    );
}

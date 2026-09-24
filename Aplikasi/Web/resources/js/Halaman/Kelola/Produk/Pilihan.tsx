import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { PindahkanItem } from '@/Komponen/Katalog/BantuanKatalog';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsPilihanProduk } from '@/Tipe/Katalog';

type Kelompok = PropsPilihanProduk['Terpasang'][number];

const kelasTautanKecil =
    'text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:text-teks-sekunder disabled:no-underline';

/** F-03 pilihan (modifier) produk: pasang, lepas, dan urutkan kelompok pilihan. Anak varian mewarisi dari induk. */
export default function HalamanPilihanProduk({ Kepala, Terpasang, Tersedia, DariInduk, Izin }: PropsPilihanProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [terpasang, AturTerpasang] = useState<Kelompok[]>(Terpasang);
    const [memproses, AturMemproses] = useState(false);
    const bolehUbah = Izin.Kelola && !DariInduk;
    const semua = [...Terpasang, ...Tersedia];
    const tersedia = semua.filter((item) => !terpasang.some((pasang) => pasang.Uuid === item.Uuid));

    const Simpan = () =>
        router.put(
            `/kelola/produk/${Kepala.Uuid}/pilihan`,
            { KelompokPilihan: terpasang.map((item) => item.Uuid) },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );

    return (
        <TataLetakAplikasi judul={`Pilihan ${Kepala.Nama}`}>
            <KepalaProduk kepala={Kepala} tabAktif="Pilihan" />
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="pilihan produk ini" /> : null}
            <DaftarGalatServer galat={props.errors} />
            {DariInduk ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-3 text-isi text-teks-sekunder">
                    Varian memakai pilihan dari produk induknya.{' '}
                    {Kepala.UuidInduk ? (
                        <Link
                            href={`/kelola/produk/${Kepala.UuidInduk}/pilihan`}
                            className="font-semibold text-brand underline"
                        >
                            Ubah pilihan di {Kepala.NamaInduk ?? 'produk induk'}
                        </Link>
                    ) : null}
                </p>
            ) : null}

            {semua.length === 0 ? (
                <KeadaanKosong judul="Belum ada kelompok pilihan, misal Level gula atau Topping.">
                    <Link href="/kelola/kelompok-pilihan" className="font-semibold text-brand underline">
                        Buat kelompok pilihan
                    </Link>
                </KeadaanKosong>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    <section
                        aria-labelledby="judul-terpasang"
                        className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                    >
                        <h2 id="judul-terpasang" className="text-subjudul font-semibold text-teks-utama">
                            Terpasang ({terpasang.length})
                        </h2>
                        <p className="text-keterangan text-teks-sekunder">Urutan di sini = urutan tampil di kasir.</p>
                        {terpasang.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">Produk ini dijual tanpa pilihan.</p>
                        ) : (
                            <ol className="flex flex-col divide-y divide-garis">
                                {terpasang.map((item, indeks) => (
                                    <li
                                        key={item.Uuid}
                                        className="flex flex-wrap items-start justify-between gap-2 py-2"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words text-teks-utama">
                                                {indeks + 1}. {item.Nama}
                                            </span>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {item.Ringkasan}
                                            </span>
                                        </span>
                                        {bolehUbah ? (
                                            <span className="flex gap-3">
                                                <button
                                                    type="button"
                                                    disabled={indeks === 0}
                                                    onClick={() =>
                                                        AturTerpasang(PindahkanItem(terpasang, indeks, indeks - 1))
                                                    }
                                                    className={kelasTautanKecil}
                                                    aria-label={`Naikkan ${item.Nama}`}
                                                >
                                                    Naik
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={indeks === terpasang.length - 1}
                                                    onClick={() =>
                                                        AturTerpasang(PindahkanItem(terpasang, indeks, indeks + 1))
                                                    }
                                                    className={kelasTautanKecil}
                                                    aria-label={`Turunkan ${item.Nama}`}
                                                >
                                                    Turun
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        AturTerpasang(
                                                            terpasang.filter((lain) => lain.Uuid !== item.Uuid),
                                                        )
                                                    }
                                                    className="text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                    aria-label={`Lepas ${item.Nama}`}
                                                >
                                                    Lepas
                                                </button>
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                    <section
                        aria-labelledby="judul-tersedia"
                        className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                    >
                        <h2 id="judul-tersedia" className="text-subjudul font-semibold text-teks-utama">
                            Tersedia ({tersedia.length})
                        </h2>
                        {tersedia.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">Semua kelompok pilihan sudah terpasang.</p>
                        ) : (
                            <ul className="flex flex-col divide-y divide-garis">
                                {tersedia.map((item) => (
                                    <li
                                        key={item.Uuid}
                                        className="flex flex-wrap items-start justify-between gap-2 py-2"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words text-teks-utama">
                                                {item.Nama}
                                            </span>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {item.Ringkasan}
                                            </span>
                                        </span>
                                        {bolehUbah ? (
                                            <button
                                                type="button"
                                                onClick={() => AturTerpasang([...terpasang, item])}
                                                className={kelasTautanKecil}
                                                aria-label={`Pasang ${item.Nama}`}
                                            >
                                                Pasang
                                            </button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            )}
            {bolehUbah && semua.length > 0 ? (
                <div>
                    <Tombol onClick={Simpan} memproses={memproses}>
                        Simpan pilihan produk
                    </Tombol>
                </div>
            ) : null}
        </TataLetakAplikasi>
    );
}

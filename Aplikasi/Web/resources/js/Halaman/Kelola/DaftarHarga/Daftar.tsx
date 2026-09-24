import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormDaftarHarga, { DaftarHargaKosong } from '@/Komponen/Katalog/FormDaftarHarga';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisDaftarHarga, PropsDaftarDaftarHarga } from '@/Tipe/Katalog';

/** Ringkasan periode berlaku: "Selalu", "Mulai …", "Sampai …", atau "… – …". */
export function RingkasPeriode(baris: Pick<BarisDaftarHarga, 'MulaiPada' | 'SelesaiPada'>): string {
    if (baris.MulaiPada === null && baris.SelesaiPada === null) {
        return 'Selalu';
    }

    if (baris.SelesaiPada === null) {
        return `Mulai ${FormatTanggalWaktu(baris.MulaiPada)}`;
    }

    if (baris.MulaiPada === null) {
        return `Sampai ${FormatTanggalWaktu(baris.SelesaiPada)}`;
    }

    return `${FormatTanggalWaktu(baris.MulaiPada)} – ${FormatTanggalWaktu(baris.SelesaiPada)}`;
}

/** F-03 daftar harga per outlet, kanal, tingkat pelanggan, dan periode. Tidak pernah dihapus, hanya dinonaktifkan. */
export default function HalamanDaftarDaftarHarga({
    DaftarHarga,
    Outlet,
    Kanal,
    ZonaWaktu,
    Izin,
}: PropsDaftarDaftarHarga) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [formTerbuka, AturFormTerbuka] = useState(false);

    return (
        <TataLetakAplikasi judul="Daftar harga">
            {!Izin.UbahHarga ? <PesanHanyaLihat izin="produk.harga.ubah" objek="daftar harga" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={formTerbuka ? Object.keys(props.errors) : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="max-w-2xl text-isi text-teks-sekunder">
                    Harga khusus untuk outlet, kanal (misal online), tingkat pelanggan, atau periode tertentu. Bila
                    beberapa daftar cocok, prioritas terbesar dipakai; bila sama, yang syaratnya lebih spesifik. Produk
                    tanpa harga di daftar memakai harga dasar.
                </p>
                {Izin.UbahHarga && !formTerbuka ? (
                    <Tombol onClick={() => AturFormTerbuka(true)}>Buat daftar harga</Tombol>
                ) : null}
            </div>
            {formTerbuka ? (
                <FormDaftarHarga
                    uuid={null}
                    awal={DaftarHargaKosong}
                    outlet={Outlet}
                    kanal={Kanal}
                    zonaWaktu={ZonaWaktu}
                    saatSelesai={() => AturFormTerbuka(false)}
                />
            ) : null}

            {DaftarHarga.Data.length === 0 ? (
                <KeadaanKosong judul="Belum ada daftar harga. Semua produk memakai harga dasar." />
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[860px] text-left text-isi">
                        <caption className="sr-only">Daftar harga, {DaftarHarga.Total} daftar</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Berlaku untuk
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Periode
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Prioritas
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Produk
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                                {Izin.UbahHarga ? (
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody>
                            {DaftarHarga.Data.map((daftar) => (
                                <tr key={daftar.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="px-4 py-2">
                                        <Link
                                            href={`/kelola/daftar-harga/${daftar.Uuid}`}
                                            className="font-semibold break-words text-brand underline"
                                        >
                                            {daftar.Nama}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {[
                                            daftar.NamaOutlet === null ? 'Semua outlet' : daftar.NamaOutlet.join(', '),
                                            daftar.LabelKanal ?? 'Semua kanal',
                                            daftar.TierPelanggan ? `Pelanggan ${daftar.TierPelanggan}` : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">{RingkasPeriode(daftar)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{daftar.Prioritas}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{daftar.JumlahProduk}</td>
                                    <td className="px-4 py-2">
                                        <LabelStatus
                                            jenis={daftar.Aktif ? 'sukses' : 'netral'}
                                            teks={daftar.Aktif ? 'Aktif' : 'Nonaktif'}
                                        />
                                    </td>
                                    {Izin.UbahHarga ? (
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        `/kelola/daftar-harga/${daftar.Uuid}/${daftar.Aktif ? 'nonaktifkan' : 'aktifkan'}`,
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                                className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                aria-label={`${daftar.Aktif ? 'Nonaktifkan' : 'Aktifkan'} ${daftar.Nama}`}
                                            >
                                                {daftar.Aktif ? 'Nonaktifkan' : 'Aktifkan'}
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
                alamat="/kelola/daftar-harga"
                saring={{}}
                halamanSaatIni={DaftarHarga.HalamanSaatIni}
                halamanTerakhir={DaftarHarga.HalamanTerakhir}
                total={DaftarHarga.Total}
                label="Halaman daftar harga"
            />
        </TataLetakAplikasi>
    );
}

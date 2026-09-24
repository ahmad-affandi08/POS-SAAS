import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { JenisKomponenPaket } from '@/Komponen/Katalog/BantuanKatalog';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import { BandingkanDesimal, CekDesimalPositif, FormatMasukanJumlah, JumlahkanDesimal } from '@/Pustaka/MasukanJumlah';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsKomponenProduk } from '@/Tipe/Katalog';

type BarisKomponen = PropsKomponenProduk['Komponen'][number];

/**
 * Alokasi harga paket (DesainF03 C.4, H.5): semua kosong (otomatis) atau semua diisi dengan total tepat 100 %.
 * Dihitung dengan penjumlahan desimal string, tanpa float.
 */
export function PeriksaAlokasiHarga(komponen: BarisKomponen[]): { total: string | null; pesan: string | null } {
    const terisi = komponen.filter((item) => item.AlokasiHarga !== '');

    if (terisi.length === 0) {
        return { total: null, pesan: null };
    }

    const total = JumlahkanDesimal(terisi.map((item) => item.AlokasiHarga));

    if (terisi.length !== komponen.length) {
        return { total, pesan: 'Isi alokasi semua komponen, atau kosongkan semuanya agar dibagi otomatis.' };
    }

    return BandingkanDesimal(total, '100') === 0
        ? { total, pesan: null }
        : { total, pesan: `Total alokasi harus tepat 100 %, sekarang ${FormatMasukanJumlah(total)} %.` };
}

/** F-03 isi paket (PaketProdukDetail): komponen, jumlah, dan alokasi harga untuk laporan per komponen. */
export default function HalamanKomponenProduk({ Kepala, Komponen, Izin }: PropsKomponenProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [komponen, AturKomponen] = useState<BarisKomponen[]>(Komponen);
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const alokasi = PeriksaAlokasiHarga(komponen);
    const Ubah = (indeks: number, perubahan: Partial<BarisKomponen>) =>
        AturKomponen(komponen.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)));

    const Simpan = () => {
        AturPeriksa(true);

        if (alokasi.pesan !== null || komponen.some((item) => !CekDesimalPositif(item.Jumlah))) {
            return;
        }

        router.put(
            `/kelola/produk/${Kepala.Uuid}/komponen`,
            {
                Komponen: komponen.map((item) => ({
                    UuidProdukKomponen: item.UuidProdukKomponen,
                    Jumlah: item.Jumlah,
                    AlokasiHarga: item.AlokasiHarga,
                })),
            },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <TataLetakAplikasi judul={`Isi paket ${Kepala.Nama}`}>
            <KepalaProduk kepala={Kepala} tabAktif="Komponen" />
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="isi paket ini" /> : null}
            <DaftarGalatServer
                galat={galat}
                kecuali={Object.keys(galat).filter((kunci) => /^Komponen\./.test(kunci))}
            />

            {komponen.length === 0 && !Izin.Kelola ? (
                <KeadaanKosong judul="Paket ini belum berisi produk." />
            ) : (
                <section
                    aria-labelledby="judul-komponen"
                    className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-komponen" className="text-subjudul font-semibold text-teks-utama">
                        Isi paket
                    </h2>
                    <p className="text-keterangan text-teks-sekunder">
                        Stok setiap komponen terpotong saat paket terjual. Alokasi harga membagi harga paket ke tiap
                        komponen untuk laporan; kosongkan semua agar dibagi otomatis menurut harga dasar.
                    </p>
                    {komponen.length === 0 ? (
                        <p className="rounded-kontrol border border-dashed border-garis-input px-3 py-2 text-isi text-teks-sekunder">
                            Paket belum berisi produk. Cari produk di bawah untuk menambahkannya.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[620px] text-left text-isi">
                                <caption className="sr-only">Komponen paket</caption>
                                <thead className="border-b border-garis text-label text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className="py-2 pr-2 font-semibold">
                                            Produk
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Jumlah
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Alokasi harga
                                        </th>
                                        <th scope="col" className="py-2 pl-2 font-semibold">
                                            <span className="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {komponen.map((item, indeks) => (
                                        <tr
                                            key={item.UuidProdukKomponen}
                                            className="border-b border-garis align-top last:border-b-0"
                                        >
                                            <th scope="row" className="py-2 pr-2 text-left font-normal">
                                                <span className="block font-semibold break-words text-teks-utama">
                                                    {item.Nama}
                                                </span>
                                                <span className="font-mono text-keterangan text-teks-sekunder">
                                                    {item.Sku ?? 'Tanpa SKU'}
                                                </span>
                                            </th>
                                            <td className="px-2 py-2">
                                                <BidangJumlah
                                                    label={`Jumlah ${item.Nama}`}
                                                    labelTersembunyi
                                                    nilai={item.Jumlah}
                                                    saatBerubah={(nilai) => Ubah(indeks, { Jumlah: nilai })}
                                                    akhiran={item.SimbolSatuan}
                                                    galat={
                                                        galat[`Komponen.${String(indeks)}.Jumlah`] ??
                                                        (periksa && !CekDesimalPositif(item.Jumlah)
                                                            ? 'Isi jumlah lebih dari 0.'
                                                            : undefined)
                                                    }
                                                    disabled={!Izin.Kelola}
                                                />
                                            </td>
                                            <td className="px-2 py-2">
                                                <BidangJumlah
                                                    label={`Alokasi harga ${item.Nama}`}
                                                    labelTersembunyi
                                                    nilai={item.AlokasiHarga}
                                                    saatBerubah={(nilai) => Ubah(indeks, { AlokasiHarga: nilai })}
                                                    desimal={6}
                                                    digitBulat={3}
                                                    akhiran="%"
                                                    galat={galat[`Komponen.${String(indeks)}.AlokasiHarga`]}
                                                    disabled={!Izin.Kelola}
                                                />
                                            </td>
                                            <td className="py-2 pl-2">
                                                {Izin.Kelola ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            AturKomponen(komponen.filter((_, i) => i !== indeks))
                                                        }
                                                        className="h-10 text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                        aria-label={`Hapus komponen ${item.Nama}`}
                                                    >
                                                        Hapus
                                                    </button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                {alokasi.total !== null ? (
                                    <tfoot>
                                        <tr>
                                            <th scope="row" colSpan={2} className="py-2 pr-2 text-right font-semibold">
                                                Total alokasi
                                            </th>
                                            <td className="px-2 py-2 text-right font-semibold tabular-nums">
                                                {FormatMasukanJumlah(alokasi.total)} %
                                            </td>
                                            <td />
                                        </tr>
                                    </tfoot>
                                ) : null}
                            </table>
                        </div>
                    )}
                    <div aria-live="polite">
                        {(periksa || alokasi.total !== null) && alokasi.pesan ? (
                            <p className="text-keterangan font-semibold text-bahaya">{alokasi.pesan}</p>
                        ) : null}
                        {galat.Komponen ? (
                            <p className="text-keterangan font-semibold text-bahaya">{galat.Komponen}</p>
                        ) : null}
                    </div>
                    {Izin.Kelola ? (
                        <>
                            <PemilihProduk
                                label="Tambah produk ke paket"
                                jenis={JenisKomponenPaket}
                                kecuali={[Kepala.Uuid, ...komponen.map((item) => item.UuidProdukKomponen)]}
                                saatPilih={(produk) =>
                                    AturKomponen([
                                        ...komponen,
                                        {
                                            UuidProdukKomponen: produk.Uuid,
                                            Nama: produk.Nama,
                                            Sku: produk.Sku,
                                            Jumlah: '1',
                                            SimbolSatuan:
                                                produk.Satuan.find((satuan) => satuan.Uuid === produk.UuidSatuanDasar)
                                                    ?.Simbol ?? '',
                                            AlokasiHarga: '',
                                        },
                                    ])
                                }
                            />
                            <div>
                                <Tombol onClick={Simpan} memproses={memproses}>
                                    Simpan isi paket
                                </Tombol>
                            </div>
                        </>
                    ) : null}
                </section>
            )}
        </TataLetakAplikasi>
    );
}

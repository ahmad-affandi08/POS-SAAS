import { router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { BandingkanDesimal, CekDesimalValid } from '@/Pustaka/MasukanJumlah';
import type { BarisBatasStok } from '@/Tipe/Katalog';

import BidangJumlah from './BidangJumlah';

/** Galat lokal per gudang: minimum tidak boleh melebihi maksimum (BatasStokTidakValid). */
export function PeriksaBatasStok(baris: BarisBatasStok[]): Record<string, string> {
    const galat: Record<string, string> = {};

    baris.forEach((item) => {
        if (
            item.StokMaksimum !== '' &&
            CekDesimalValid(item.StokMaksimum) &&
            CekDesimalValid(item.StokMinimum || '0') &&
            BandingkanDesimal(item.StokMinimum || '0', item.StokMaksimum) > 0
        ) {
            galat[item.UuidGudang] = 'Stok minimum tidak boleh lebih besar dari stok maksimum.';
        }
    });

    return galat;
}

type PropsFormBatasStok = {
    uuidProduk: string;
    baris: BarisBatasStok[];
    simbolSatuan: string;
    bolehDesimal: boolean;
    bolehUbah: boolean;
    galat: Record<string, string | undefined>;
};

/** Stok minimum/maksimum per lokasi stok (ProdukGudang). Hanya pengguna dengan persediaan.kelola yang bisa mengubah. */
export default function FormBatasStok({
    uuidProduk,
    baris: barisAwal,
    simbolSatuan,
    bolehDesimal,
    bolehUbah,
    galat,
}: PropsFormBatasStok) {
    const [baris, AturBaris] = useState(barisAwal);
    const [memproses, AturMemproses] = useState(false);
    const galatLokal = PeriksaBatasStok(baris);
    const Ubah = (indeks: number, perubahan: Partial<BarisBatasStok>) =>
        AturBaris(baris.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)));

    const Simpan = () => {
        if (Object.keys(galatLokal).length > 0) {
            return;
        }

        router.put(
            `/kelola/produk/${uuidProduk}/batas-stok`,
            {
                Baris: baris.map((item) => ({
                    UuidGudang: item.UuidGudang,
                    StokMinimum: item.StokMinimum,
                    StokMaksimum: item.StokMaksimum,
                })),
            },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <section
            aria-labelledby="judul-batas-stok"
            className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
        >
            <h2 id="judul-batas-stok" className="text-subjudul font-semibold text-teks-utama">
                Stok minimum & maksimum per lokasi
            </h2>
            <p className="text-keterangan text-teks-sekunder">
                Dipakai untuk peringatan stok menipis dan saran pembelian. Kosongkan maksimum bila tidak dibatasi.
            </p>
            {baris.length === 0 ? (
                <p className="text-isi text-teks-sekunder">
                    Belum ada lokasi stok aktif. Tambah gudang di menu Outlet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[560px] text-left text-isi">
                        <caption className="sr-only">Batas stok per lokasi</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="py-2 pr-2 font-semibold">
                                    Lokasi stok
                                </th>
                                <th scope="col" className="px-2 py-2 text-right font-semibold">
                                    Minimum ({simbolSatuan})
                                </th>
                                <th scope="col" className="py-2 pl-2 text-right font-semibold">
                                    Maksimum ({simbolSatuan})
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {baris.map((item, indeks) => {
                                const pesan =
                                    galatLokal[item.UuidGudang] ?? galat[`Baris.${String(indeks)}.StokMaksimum`];

                                return (
                                    <tr
                                        key={item.UuidGudang}
                                        className="border-b border-garis align-top last:border-b-0"
                                    >
                                        <th scope="row" className="py-2 pr-2 text-left font-normal">
                                            <span className="font-semibold text-teks-utama">{item.NamaGudang}</span>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {item.NamaOutlet}
                                            </span>
                                        </th>
                                        <td className="px-2 py-2">
                                            <BidangJumlah
                                                label={`Stok minimum ${item.NamaGudang}`}
                                                labelTersembunyi
                                                nilai={item.StokMinimum}
                                                saatBerubah={(nilai) => Ubah(indeks, { StokMinimum: nilai })}
                                                desimal={bolehDesimal ? 4 : 0}
                                                disabled={!bolehUbah}
                                                galat={galat[`Baris.${String(indeks)}.StokMinimum`]}
                                            />
                                        </td>
                                        <td className="py-2 pl-2">
                                            <BidangJumlah
                                                label={`Stok maksimum ${item.NamaGudang}`}
                                                labelTersembunyi
                                                nilai={item.StokMaksimum}
                                                saatBerubah={(nilai) => Ubah(indeks, { StokMaksimum: nilai })}
                                                desimal={bolehDesimal ? 4 : 0}
                                                disabled={!bolehUbah}
                                                galat={pesan}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            {bolehUbah && baris.length > 0 ? (
                <div>
                    <Tombol onClick={Simpan} memproses={memproses} disabled={Object.keys(galatLokal).length > 0}>
                        Simpan batas stok
                    </Tombol>
                </div>
            ) : null}
            {!bolehUbah ? (
                <p className="text-keterangan text-teks-sekunder">
                    Perlu izin <span className="font-mono">persediaan.kelola</span> untuk mengubah batas stok.
                </p>
            ) : null}
        </section>
    );
}

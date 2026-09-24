import { router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { BandingkanDesimal, CekDesimalValid } from '@/Pustaka/MasukanJumlah';
import type { BarisBatasStok } from '@/Tipe/Katalog';

import BidangJumlah from './BidangJumlah';
import PanelKatalog from './PanelKatalog';

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
        <PanelKatalog
            judul="Stok minimum & maksimum per lokasi"
            idJudul="judul-batas-stok"
            keterangan="Dipakai untuk peringatan stok menipis dan saran pembelian. Kosongkan maksimum bila tidak dibatasi."
        >
            {baris.length === 0 ? (
                <p className="text-isi text-teks-sekunder">
                    Belum ada lokasi stok aktif. Tambah gudang di menu Outlet.
                </p>
            ) : (
                <Table className="min-w-[560px] text-left text-isi">
                    <TableCaption className="sr-only">Batas stok per lokasi</TableCaption>
                    <TableHeader>
                        <TableRow className="border-garis hover:bg-transparent">
                            <TableHead scope="col" className="pl-0 text-label font-semibold text-teks-sekunder">
                                Lokasi stok
                            </TableHead>
                            <TableHead scope="col" className="text-right text-label font-semibold text-teks-sekunder">
                                Minimum ({simbolSatuan})
                            </TableHead>
                            <TableHead
                                scope="col"
                                className="pr-0 text-right text-label font-semibold text-teks-sekunder"
                            >
                                Maksimum ({simbolSatuan})
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {baris.map((item, indeks) => {
                            const pesan = galatLokal[item.UuidGudang] ?? galat[`Baris.${String(indeks)}.StokMaksimum`];

                            return (
                                <TableRow key={item.UuidGudang} className="border-garis align-top hover:bg-transparent">
                                    <th scope="row" className="p-2 pl-0 text-left align-top font-normal">
                                        <span className="font-semibold text-teks-utama">{item.NamaGudang}</span>
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {item.NamaOutlet}
                                        </span>
                                    </th>
                                    <TableCell className="whitespace-normal">
                                        <BidangJumlah
                                            label={`Stok minimum ${item.NamaGudang}`}
                                            labelTersembunyi
                                            nilai={item.StokMinimum}
                                            saatBerubah={(nilai) => Ubah(indeks, { StokMinimum: nilai })}
                                            desimal={bolehDesimal ? 4 : 0}
                                            disabled={!bolehUbah}
                                            galat={galat[`Baris.${String(indeks)}.StokMinimum`]}
                                        />
                                    </TableCell>
                                    <TableCell className="pr-0 whitespace-normal">
                                        <BidangJumlah
                                            label={`Stok maksimum ${item.NamaGudang}`}
                                            labelTersembunyi
                                            nilai={item.StokMaksimum}
                                            saatBerubah={(nilai) => Ubah(indeks, { StokMaksimum: nilai })}
                                            desimal={bolehDesimal ? 4 : 0}
                                            disabled={!bolehUbah}
                                            galat={pesan}
                                        />
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
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
        </PanelKatalog>
    );
}

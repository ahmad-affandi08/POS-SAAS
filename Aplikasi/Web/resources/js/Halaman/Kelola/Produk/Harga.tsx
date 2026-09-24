import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { AmbilGalatBerawalan } from '@/Komponen/Katalog/BantuanKatalog';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TabelHargaBertingkat, { PeriksaBarisHarga } from '@/Komponen/Katalog/TabelHargaBertingkat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { BandingkanDesimal, CekDesimalValid, FormatMasukanJumlah } from '@/Pustaka/MasukanJumlah';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisHarga, PropsHargaProduk } from '@/Tipe/Katalog';

type SatuanHarga = PropsHargaProduk['Satuan'][number];
type DaftarHargaProduk = PropsHargaProduk['DaftarHarga'][number];

/** Ratakan harga daftar harga per satuan menjadi body `{ Harga: (BarisHarga & {UuidProdukSatuan})[] }` (DesainF03 E.6). */
export function RatakanHargaDaftar(
    satuan: SatuanHarga[],
    perSatuan: Record<string, BarisHarga[]>,
): { baris: (BarisHarga & { UuidProdukSatuan: string })[]; awal: Record<string, number> } {
    const baris: (BarisHarga & { UuidProdukSatuan: string })[] = [];
    const awal: Record<string, number> = {};

    satuan.forEach((item) => {
        awal[item.UuidProdukSatuan] = baris.length;
        (perSatuan[item.UuidProdukSatuan] ?? []).forEach((harga) =>
            baris.push({ ...harga, UuidProdukSatuan: item.UuidProdukSatuan }),
        );
    });

    return { baris, awal };
}

/** Galat server `Harga.{j}.…` dari body rata dipetakan kembali ke tabel satuan (kunci relatif `{i}.…`). */
function GalatSatuanDaftar(
    galat: Record<string, string | undefined>,
    mulai: number,
    jumlah: number,
): Record<string, string | undefined> {
    const hasil: Record<string, string | undefined> = {};

    for (let i = 0; i < jumlah; i += 1) {
        const bagian = AmbilGalatBerawalan(galat, `Harga.${String(mulai + i)}`);

        Object.entries(bagian).forEach(([kunci, pesan]) => {
            hasil[`${String(i)}.${kunci}`] = pesan;
        });
    }

    return hasil;
}

function EditorDaftarHarga({
    uuidProduk,
    daftar,
    satuan,
    bolehUbah,
    galat,
}: {
    uuidProduk: string;
    daftar: DaftarHargaProduk;
    satuan: SatuanHarga[];
    bolehUbah: boolean;
    galat: Record<string, string | undefined>;
}) {
    const [perSatuan, AturPerSatuan] = useState<Record<string, BarisHarga[]>>(() =>
        Object.fromEntries(
            satuan.map((item) => [
                item.UuidProdukSatuan,
                daftar.Harga.filter((harga) => harga.UuidProdukSatuan === item.UuidProdukSatuan).map((harga) => ({
                    JumlahMinimum: harga.JumlahMinimum,
                    Harga: harga.Harga,
                })),
            ]),
        ),
    );
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const { baris, awal } = RatakanHargaDaftar(satuan, perSatuan);

    const Simpan = () => {
        AturPeriksa(true);
        const salah = satuan.some((item) => {
            const hasil = PeriksaBarisHarga(perSatuan[item.UuidProdukSatuan] ?? [], {
                wajibDasar: false,
                bolehDesimal: item.BolehDesimal,
            });

            return Object.keys(hasil.perBaris).length > 0;
        });

        if (salah) {
            return;
        }

        router.put(
            `/kelola/produk/${uuidProduk}/harga/daftar-harga/${daftar.Uuid}`,
            { Harga: baris },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <PanelKatalog
            tingkat="h3"
            idJudul={`judul-daftar-${daftar.Uuid}`}
            judul={
                <Link href={`/kelola/daftar-harga/${daftar.Uuid}`} className="text-brand underline">
                    {daftar.Nama}
                </Link>
            }
            aksi={<LabelStatus jenis={daftar.Aktif ? 'sukses' : 'netral'} teks={daftar.Aktif ? 'Aktif' : 'Nonaktif'} />}
            keterangan={`${daftar.Ringkasan}. Kosongkan satuan yang memakai harga dasar.`}
        >
            {satuan.map((item) => (
                <div key={item.UuidProdukSatuan} className="flex flex-col gap-1">
                    <p className="text-label font-semibold text-teks-utama">
                        Per {item.Nama} ({item.Simbol})
                    </p>
                    <TabelHargaBertingkat
                        judul={`${daftar.Nama}: harga per ${item.Simbol}`}
                        baris={perSatuan[item.UuidProdukSatuan] ?? []}
                        saatBerubah={(nilai) => AturPerSatuan({ ...perSatuan, [item.UuidProdukSatuan]: nilai })}
                        simbolSatuan={item.Simbol}
                        bolehDesimal={item.BolehDesimal}
                        wajibDasar={false}
                        galatServer={GalatSatuanDaftar(
                            galat,
                            awal[item.UuidProdukSatuan] ?? 0,
                            (perSatuan[item.UuidProdukSatuan] ?? []).length,
                        )}
                        tampilkanGalat={periksa}
                        disabled={!bolehUbah}
                    />
                </div>
            ))}
            {bolehUbah ? (
                <div>
                    <Tombol varian="sekunder" onClick={Simpan} memproses={memproses}>
                        Simpan harga {daftar.Nama}
                    </Tombol>
                </div>
            ) : null}
        </PanelKatalog>
    );
}

/** F-03 harga produk: harga dasar & bertingkat per satuan, harga per daftar harga, riwayat harga (BR-03.3). */
export default function HalamanHargaProduk({
    Kepala,
    Satuan,
    DaftarHarga,
    Riwayat,
    LabelHargaTermasukPajak,
    Izin,
}: PropsHargaProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [hargaDasar, AturHargaDasar] = useState<Record<string, BarisHarga[]>>(() =>
        Object.fromEntries(Satuan.map((item) => [item.UuidProdukSatuan, item.HargaDasar])),
    );
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);

    const SimpanDasar = () => {
        AturPeriksa(true);
        const salah = Satuan.some((item) => {
            const hasil = PeriksaBarisHarga(hargaDasar[item.UuidProdukSatuan] ?? [], {
                wajibDasar: true,
                bolehDesimal: item.BolehDesimal,
            });

            return Object.keys(hasil.perBaris).length > 0 || hasil.umum !== null;
        });

        if (salah) {
            return;
        }

        router.put(
            `/kelola/produk/${Kepala.Uuid}/harga`,
            {
                Satuan: Satuan.map((item) => ({
                    UuidProdukSatuan: item.UuidProdukSatuan,
                    Harga: hargaDasar[item.UuidProdukSatuan] ?? [],
                })),
            },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <TataLetakAplikasi judul={`Harga ${Kepala.Nama}`}>
            <KepalaProduk kepala={Kepala} tabAktif="Harga" />
            {!Izin.UbahHarga ? <PesanHanyaLihat izin="produk.harga.ubah" objek="harga produk ini" /> : null}
            <DaftarGalatServer
                galat={galat}
                kecuali={Object.keys(galat).filter((kunci) => /^(Satuan|Harga)\./.test(kunci))}
            />

            <PanelKatalog
                judul="Harga dasar & harga bertingkat"
                idJudul="judul-harga-dasar"
                keterangan={`Harga ${LabelHargaTermasukPajak}. Satuan tanpa harga dasar hanya dipakai untuk pembelian dan tidak muncul di kasir. Harga satuan lain tidak dihitung otomatis dari isi satuan.`}
            >
                {Satuan.map((item, indeks) => (
                    <div key={item.UuidProdukSatuan} className="flex flex-col gap-1">
                        <p className="text-label font-semibold text-teks-utama">
                            Per {item.Nama} ({item.Simbol})
                            {CekDesimalValid(item.KonversiKeDasar) &&
                            BandingkanDesimal(item.KonversiKeDasar, '1') !== 0 ? (
                                <span className="font-normal text-teks-sekunder tabular-nums">
                                    {' '}
                                    · isi {FormatMasukanJumlah(item.KonversiKeDasar)} satuan dasar
                                </span>
                            ) : null}
                        </p>
                        <TabelHargaBertingkat
                            judul={`Harga per ${item.Simbol}`}
                            baris={hargaDasar[item.UuidProdukSatuan] ?? []}
                            saatBerubah={(nilai) => AturHargaDasar({ ...hargaDasar, [item.UuidProdukSatuan]: nilai })}
                            simbolSatuan={item.Simbol}
                            bolehDesimal={item.BolehDesimal}
                            wajibDasar
                            galatServer={AmbilGalatBerawalan(galat, `Satuan.${String(indeks)}.Harga`)}
                            tampilkanGalat={periksa}
                            disabled={!Izin.UbahHarga}
                        />
                    </div>
                ))}
                {Izin.UbahHarga ? (
                    <div>
                        <Tombol onClick={SimpanDasar} memproses={memproses}>
                            Simpan harga dasar
                        </Tombol>
                    </div>
                ) : null}
            </PanelKatalog>

            <section aria-labelledby="judul-daftar-harga" className="flex flex-col gap-3">
                <h2 id="judul-daftar-harga" className="text-subjudul font-semibold text-teks-utama">
                    Harga di daftar harga
                </h2>
                {DaftarHarga.length === 0 ? (
                    <p className="rounded-panel border border-garis bg-card px-4 py-3 text-isi text-teks-sekunder">
                        Belum ada daftar harga. Buat daftar harga untuk harga per outlet, kanal (misal online), tingkat
                        pelanggan, atau periode promo di{' '}
                        <Link href="/kelola/daftar-harga" className="font-semibold text-brand underline">
                            menu Daftar harga
                        </Link>
                        .
                    </p>
                ) : (
                    DaftarHarga.map((daftar) => (
                        <EditorDaftarHarga
                            key={daftar.Uuid}
                            uuidProduk={Kepala.Uuid}
                            daftar={daftar}
                            satuan={Satuan}
                            bolehUbah={Izin.UbahHarga}
                            galat={galat}
                        />
                    ))
                )}
            </section>

            <PanelKatalog judul="Riwayat harga" idJudul="judul-riwayat-harga">
                {Riwayat.Data.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Belum ada perubahan harga.</p>
                ) : (
                    <Table className="min-w-[760px] text-left text-label">
                        <TableCaption className="sr-only">Riwayat perubahan harga</TableCaption>
                        <TableHeader>
                            <TableRow className="border-garis hover:bg-transparent">
                                <TableHead scope="col" className="pl-0 font-semibold text-teks-sekunder">
                                    Waktu
                                </TableHead>
                                <TableHead scope="col" className="font-semibold text-teks-sekunder">
                                    Harga
                                </TableHead>
                                <TableHead scope="col" className="text-right font-semibold text-teks-sekunder">
                                    Mulai jumlah
                                </TableHead>
                                <TableHead scope="col" className="text-right font-semibold text-teks-sekunder">
                                    Lama
                                </TableHead>
                                <TableHead scope="col" className="text-right font-semibold text-teks-sekunder">
                                    Baru
                                </TableHead>
                                <TableHead scope="col" className="pr-0 font-semibold text-teks-sekunder">
                                    Oleh
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Riwayat.Data.map((item, indeks) => (
                                <TableRow key={`${item.DibuatPada}-${String(indeks)}`} className="border-garis">
                                    <TableCell className="pl-0 text-teks-sekunder">
                                        {FormatTanggalWaktu(item.DibuatPada)}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        {item.NamaDaftarHarga ?? 'Harga dasar'} · {item.NamaSatuan}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {FormatMasukanJumlah(item.JumlahMinimum)}+
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums text-teks-sekunder">
                                        {item.HargaLama === null ? 'Baru' : FormatRupiah(item.HargaLama)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.HargaBaru === null ? 'Dihapus' : FormatRupiah(item.HargaBaru)}
                                    </TableCell>
                                    <TableCell className="pr-0 whitespace-normal text-teks-sekunder">
                                        {item.NamaPengubah ?? 'Sistem'} · {item.LabelSumber}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Paginasi
                    alamat={`/kelola/produk/${Kepala.Uuid}/harga`}
                    saring={{}}
                    halamanSaatIni={Riwayat.HalamanSaatIni}
                    halamanTerakhir={Riwayat.HalamanTerakhir}
                    total={Riwayat.Total}
                    label="Halaman riwayat harga"
                />
            </PanelKatalog>
        </TataLetakAplikasi>
    );
}

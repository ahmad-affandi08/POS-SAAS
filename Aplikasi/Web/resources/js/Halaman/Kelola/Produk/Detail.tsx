import { Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import BidangGambar from '@/Komponen/Formulir/BidangGambar';
import Tombol from '@/Komponen/Formulir/Tombol';
import { LabelTigaKeadaan } from '@/Komponen/Katalog/BantuanKatalog';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormBatasStok from '@/Komponen/Katalog/FormBatasStok';
import PembuatVarian from '@/Komponen/Katalog/PembuatVarian';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Komponen/Ui/alert-dialog';
import { Button } from '@/Komponen/Ui/button';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { FormatMasukanJumlah } from '@/Pustaka/MasukanJumlah';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDetailProduk } from '@/Tipe/Katalog';

function Baris({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5 border-b border-garis py-2 last:border-b-0 sm:flex-row sm:gap-4">
            <dt className="text-label text-teks-sekunder sm:w-48 sm:shrink-0">{label}</dt>
            <dd className="text-isi break-words text-teks-utama">{children}</dd>
        </div>
    );
}

/** F-03 ringkasan produk: info, gambar, satuan & barcode, varian, batas stok, riwayat, arsip/hapus (BR-03.2). */
export default function HalamanDetailProduk({
    Kepala,
    Produk,
    Varian,
    BatasStok,
    Riwayat,
    Jenis,
    BatasSku,
    Izin,
}: PropsDetailProduk) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [gambar, AturGambar] = useState<File | null>(null);
    const [mengunggah, AturMengunggah] = useState(false);
    const dasar = Produk.SatuanDasar;
    const induk = Produk.Jenis === 'IndukVarian';
    const opsiKirim = { preserveScroll: true };

    const Unggah = () => {
        if (gambar === null) {
            return;
        }

        router.post(
            `/kelola/produk/${Produk.Uuid}/gambar`,
            { Gambar: gambar },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => AturMengunggah(true),
                onFinish: () => AturMengunggah(false),
                onSuccess: () => AturGambar(null),
            },
        );
    };

    return (
        <TataLetakAplikasi judul={Produk.Nama}>
            <KepalaProduk kepala={Kepala} tabAktif="Ringkasan" />
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="produk ini" /> : null}
            <DaftarGalatServer galat={props.errors} />

            {Izin.Kelola ? (
                <div className="flex flex-wrap gap-2">
                    <Button asChild className="h-10">
                        <Link href={`/kelola/produk/${Produk.Uuid}/ubah`}>Ubah produk</Link>
                    </Button>
                    {Produk.DiarsipkanPada === null ? (
                        <Tombol
                            varian="sekunder"
                            onClick={() => router.post(`/kelola/produk/${Produk.Uuid}/arsipkan`, {}, opsiKirim)}
                        >
                            Arsipkan produk
                        </Tombol>
                    ) : (
                        <Tombol
                            varian="sekunder"
                            onClick={() => router.post(`/kelola/produk/${Produk.Uuid}/pulihkan`, {}, opsiKirim)}
                        >
                            Pulihkan produk
                        </Tombol>
                    )}
                    {Produk.AlasanTidakBisaDihapus === null ? (
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive" className="h-10">
                                    Hapus produk
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle className="text-subjudul font-semibold text-teks-utama">
                                        Hapus {Produk.Nama}?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription className="text-isi text-teks-sekunder">
                                        Produk, barcode, dan harganya dihapus permanen, dan SKU bisa dipakai produk
                                        lain. Bila produk hanya tidak dijual lagi, pilih Arsipkan.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Batal</AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        onClick={() => router.delete(`/kelola/produk/${Produk.Uuid}`)}
                                    >
                                        Ya, hapus produk
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    ) : null}
                </div>
            ) : null}

            {Izin.Kelola && Produk.AlasanTidakBisaDihapus !== null ? (
                <p className="text-keterangan text-teks-sekunder">
                    Produk ini tidak bisa dihapus: {Produk.AlasanTidakBisaDihapus}. Arsipkan bila tidak dijual lagi.
                </p>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-3">
                <PanelKatalog judul="Informasi produk" idJudul="judul-info" className="lg:col-span-2">
                    <dl>
                        <Baris label="Nama di struk">{Produk.NamaStruk ?? 'Sama dengan nama produk'}</Baris>
                        <Baris label="SKU">
                            <span className="font-mono">{Produk.Sku ?? '—'}</span>
                        </Baris>
                        <Baris label="Jenis">{Produk.LabelJenis}</Baris>
                        <Baris label="Kategori">{Produk.NamaKategori ?? 'Tanpa kategori'}</Baris>
                        <Baris label="Merek">{Produk.Merek ?? '—'}</Baris>
                        <Baris label="Satuan dasar">
                            {dasar.Nama} ({dasar.Simbol})
                        </Baris>
                        <Baris label="Pelacakan">{Produk.LabelPelacakan}</Baris>
                        <Baris label="Kelompok pajak">
                            {Produk.KelompokPajak
                                ? `${Produk.KelompokPajak.Nama} · ${Produk.KelompokPajak.LabelKategori}`
                                : 'Belum dipilih'}
                        </Baris>
                        <Baris label="Harga termasuk pajak">
                            {LabelTigaKeadaan(Produk.HargaTermasukPajak, 'Ikuti pengaturan outlet')}
                        </Baris>
                        {BatasStok !== null ? (
                            <Baris label="Jual saat stok kosong">
                                {LabelTigaKeadaan(Produk.BolehMinus, 'Ikuti pengaturan usaha')}
                            </Baris>
                        ) : null}
                        <Baris label="Tampil di kasir">{Produk.TampilDiPos ? 'Ya' : 'Tidak'}</Baris>
                        <Baris label="Tampil di toko online">{Produk.TampilOnline ? 'Ya' : 'Tidak'}</Baris>
                        <Baris label="Dibuat">{FormatTanggalWaktu(Produk.DibuatPada)}</Baris>
                        <Baris label="Terakhir diubah">{FormatTanggalWaktu(Produk.DiubahPada)}</Baris>
                        {Produk.DiarsipkanPada ? (
                            <Baris label="Diarsipkan">
                                <LabelStatus jenis="netral" teks="Diarsipkan" />{' '}
                                {FormatTanggalWaktu(Produk.DiarsipkanPada)}
                            </Baris>
                        ) : null}
                    </dl>
                </PanelKatalog>
                <PanelKatalog judul="Gambar" idJudul="judul-gambar">
                    <BidangGambar
                        label="Gambar produk"
                        berkas={gambar}
                        saatBerubah={AturGambar}
                        tautanSaatIni={Produk.UrlGambar}
                        {...(Izin.Kelola
                            ? {
                                  saatHapusSaatIni: () =>
                                      router.delete(`/kelola/produk/${Produk.Uuid}/gambar`, opsiKirim),
                              }
                            : {})}
                        ukuranMaksimalKb={5120}
                        ekstensi={['jpg', 'jpeg', 'png', 'webp']}
                        keterangan="Minimal 200 × 200 piksel, disarankan persegi."
                        galat={props.errors.Gambar}
                        disabled={!Izin.Kelola}
                    />
                    {Izin.Kelola && gambar !== null ? (
                        <div>
                            <Tombol onClick={Unggah} memproses={mengunggah}>
                                Simpan gambar
                            </Tombol>
                        </div>
                    ) : null}
                </PanelKatalog>
            </div>

            <PanelKatalog
                judul="Satuan & barcode"
                idJudul="judul-satuan"
                keterangan="Barcode internal (EAN-13 berawalan 20) untuk barang tanpa barcode pabrik."
            >
                <Table className="min-w-[640px] text-left text-isi">
                    <TableCaption className="sr-only">Satuan dan barcode produk</TableCaption>
                    <TableHeader>
                        <TableRow className="border-garis hover:bg-transparent">
                            <TableHead scope="col" className="pl-0 text-label font-semibold text-teks-sekunder">
                                Satuan
                            </TableHead>
                            <TableHead scope="col" className="text-right text-label font-semibold text-teks-sekunder">
                                Isi
                            </TableHead>
                            <TableHead scope="col" className="text-label font-semibold text-teks-sekunder">
                                Bawaan
                            </TableHead>
                            <TableHead scope="col" className="pr-0 text-label font-semibold text-teks-sekunder">
                                Barcode
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Produk.Satuan.map((satuan) => (
                            <TableRow key={satuan.Uuid} className="border-garis align-top">
                                <TableCell className="pl-0 whitespace-normal">
                                    <span className="font-semibold text-teks-utama">
                                        {satuan.Nama} ({satuan.Simbol})
                                    </span>
                                    <span className="block text-keterangan text-teks-sekunder">
                                        {satuan.BisaDijual
                                            ? 'Dijual di kasir'
                                            : 'Hanya untuk pembelian (belum ada harga dasar)'}
                                    </span>
                                </TableCell>
                                <TableCell className="text-right whitespace-nowrap tabular-nums">
                                    {FormatMasukanJumlah(satuan.KonversiKeDasar)} {dasar.Simbol}
                                </TableCell>
                                <TableCell className="text-teks-sekunder">
                                    {[satuan.DefaultJual ? 'Jual' : null, satuan.DefaultBeli ? 'Beli' : null]
                                        .filter(Boolean)
                                        .join(', ') || '—'}
                                </TableCell>
                                <TableCell className="pr-0 whitespace-normal">
                                    {satuan.Barcode.length === 0 ? (
                                        <span className="text-teks-sekunder">Belum ada</span>
                                    ) : (
                                        <ul className="flex flex-col gap-0.5">
                                            {satuan.Barcode.map((barcode) => (
                                                <li key={barcode.Uuid} className="font-mono text-label break-all">
                                                    {barcode.Barcode}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    {Izin.Kelola ? (
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    `/kelola/produk/${Produk.Uuid}/satuan/${satuan.Uuid}/barcode-internal`,
                                                    {},
                                                    opsiKirim,
                                                )
                                            }
                                            className="mt-1 h-auto px-0"
                                        >
                                            Buat barcode internal {satuan.Simbol}
                                        </Button>
                                    ) : null}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </PanelKatalog>

            {induk ? (
                <PanelKatalog judul={`Varian (${String(Varian.length)})`} idJudul="judul-varian">
                    {Varian.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">
                            Belum ada varian. Buat varian dari atribut di bawah.
                        </p>
                    ) : (
                        <Table className="min-w-[560px] text-left text-isi">
                            <TableCaption className="sr-only">Daftar varian</TableCaption>
                            <TableHeader>
                                <TableRow className="border-garis hover:bg-transparent">
                                    <TableHead scope="col" className="pl-0 text-label font-semibold text-teks-sekunder">
                                        Varian
                                    </TableHead>
                                    <TableHead scope="col" className="text-label font-semibold text-teks-sekunder">
                                        SKU
                                    </TableHead>
                                    <TableHead
                                        scope="col"
                                        className="text-right text-label font-semibold text-teks-sekunder"
                                    >
                                        Harga dasar
                                    </TableHead>
                                    <TableHead scope="col" className="pr-0 text-label font-semibold text-teks-sekunder">
                                        Status
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Varian.map((varian) => (
                                    <TableRow key={varian.Uuid} className="border-garis">
                                        <TableCell className="pl-0 whitespace-normal">
                                            <Link
                                                href={`/kelola/produk/${varian.Uuid}`}
                                                className="font-semibold text-brand underline"
                                            >
                                                {varian.Nama}
                                            </Link>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {varian.Atribut.map((a) => `${a.Nama}: ${a.Nilai}`).join(' · ')}
                                            </span>
                                        </TableCell>
                                        <TableCell className="font-mono text-label">{varian.Sku ?? '—'}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {varian.HargaDasar === null ? (
                                                <span className="text-teks-sekunder">Belum ada harga</span>
                                            ) : (
                                                FormatRupiah(varian.HargaDasar)
                                            )}
                                        </TableCell>
                                        <TableCell className="pr-0">
                                            <LabelStatus
                                                jenis={varian.Status === 'Aktif' ? 'sukses' : 'netral'}
                                                teks={varian.Status === 'Aktif' ? 'Aktif' : 'Diarsipkan'}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </PanelKatalog>
            ) : null}

            {induk && Izin.Kelola ? (
                <PembuatVarian
                    uuidProduk={Produk.Uuid}
                    atributAwal={Produk.AtributVarian}
                    varian={Varian}
                    jenis={Jenis}
                    batasSku={BatasSku}
                    bolehUbahHarga={Izin.UbahHarga}
                    galat={props.errors}
                />
            ) : null}

            {BatasStok !== null ? (
                <FormBatasStok
                    uuidProduk={Produk.Uuid}
                    baris={BatasStok}
                    simbolSatuan={dasar.Simbol}
                    bolehDesimal={dasar.BolehDesimal}
                    bolehUbah={Izin.KelolaPersediaan}
                    galat={props.errors}
                />
            ) : null}

            <PanelKatalog judul="Riwayat perubahan" idJudul="judul-riwayat">
                {Riwayat.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Belum ada riwayat.</p>
                ) : (
                    <ol className="flex flex-col divide-y divide-garis">
                        {Riwayat.map((item, indeks) => (
                            <li
                                key={`${item.DibuatPada}-${String(indeks)}`}
                                className="flex flex-wrap justify-between gap-2 py-2 text-isi"
                            >
                                <span className="text-teks-utama">{item.Peristiwa}</span>
                                <span className="text-keterangan text-teks-sekunder">
                                    {item.NamaPengguna ?? 'Sistem'} · {FormatTanggalWaktu(item.DibuatPada)}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </PanelKatalog>
        </TataLetakAplikasi>
    );
}

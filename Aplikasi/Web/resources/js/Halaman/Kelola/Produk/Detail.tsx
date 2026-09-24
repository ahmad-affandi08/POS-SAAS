import { Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import BidangGambar from '@/Komponen/Formulir/BidangGambar';
import Tombol from '@/Komponen/Formulir/Tombol';
import { LabelTigaKeadaan } from '@/Komponen/Katalog/BantuanKatalog';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormBatasStok from '@/Komponen/Katalog/FormBatasStok';
import GeneratorVarian from '@/Komponen/Katalog/GeneratorVarian';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
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
    const [konfirmasiHapus, AturKonfirmasiHapus] = useState(false);
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
                    <Link
                        href={`/kelola/produk/${Produk.Uuid}/ubah`}
                        className="inline-flex h-10 items-center rounded-kontrol border border-brand bg-brand px-4 text-label font-semibold text-permukaan outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                    >
                        Ubah produk
                    </Link>
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
                        <Tombol varian="bahaya" onClick={() => AturKonfirmasiHapus(true)}>
                            Hapus produk
                        </Tombol>
                    ) : null}
                </div>
            ) : null}

            {Izin.Kelola && Produk.AlasanTidakBisaDihapus !== null ? (
                <p className="text-keterangan text-teks-sekunder">
                    Produk ini tidak bisa dihapus: {Produk.AlasanTidakBisaDihapus}. Arsipkan bila tidak dijual lagi.
                </p>
            ) : null}

            {konfirmasiHapus ? (
                <div
                    role="alertdialog"
                    aria-labelledby="judul-hapus"
                    aria-describedby="isi-hapus"
                    className="flex flex-col gap-2 rounded-panel border border-l-4 border-bahaya bg-permukaan p-4"
                >
                    <p id="judul-hapus" className="text-label font-semibold text-teks-utama">
                        Hapus {Produk.Nama}?
                    </p>
                    <p id="isi-hapus" className="text-isi text-teks-sekunder">
                        Produk, barcode, dan harganya dihapus permanen, dan SKU bisa dipakai produk lain. Bila produk
                        hanya tidak dijual lagi, pilih Arsipkan.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Tombol
                            varian="bahaya"
                            autoFocus
                            onClick={() => router.delete(`/kelola/produk/${Produk.Uuid}`)}
                        >
                            Ya, hapus produk
                        </Tombol>
                        <Tombol varian="sekunder" onClick={() => AturKonfirmasiHapus(false)}>
                            Batal
                        </Tombol>
                    </div>
                </div>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-3">
                <section
                    aria-labelledby="judul-info"
                    className="rounded-panel border border-garis bg-permukaan p-4 lg:col-span-2"
                >
                    <h2 id="judul-info" className="text-subjudul font-semibold text-teks-utama">
                        Informasi produk
                    </h2>
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
                </section>
                <section
                    aria-labelledby="judul-gambar"
                    className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-gambar" className="text-subjudul font-semibold text-teks-utama">
                        Gambar
                    </h2>
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
                </section>
            </div>

            <section
                aria-labelledby="judul-satuan"
                className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
            >
                <h2 id="judul-satuan" className="text-subjudul font-semibold text-teks-utama">
                    Satuan & barcode
                </h2>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-left text-isi">
                        <caption className="sr-only">Satuan dan barcode produk</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="py-2 pr-2 font-semibold">
                                    Satuan
                                </th>
                                <th scope="col" className="px-2 py-2 text-right font-semibold">
                                    Isi
                                </th>
                                <th scope="col" className="px-2 py-2 font-semibold">
                                    Bawaan
                                </th>
                                <th scope="col" className="px-2 py-2 font-semibold">
                                    Barcode
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Produk.Satuan.map((satuan) => (
                                <tr key={satuan.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="py-2 pr-2">
                                        <span className="font-semibold text-teks-utama">
                                            {satuan.Nama} ({satuan.Simbol})
                                        </span>
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {satuan.BisaDijual
                                                ? 'Dijual di kasir'
                                                : 'Hanya untuk pembelian (belum ada harga dasar)'}
                                        </span>
                                    </td>
                                    <td className="px-2 py-2 text-right whitespace-nowrap tabular-nums">
                                        {FormatMasukanJumlah(satuan.KonversiKeDasar)} {dasar.Simbol}
                                    </td>
                                    <td className="px-2 py-2 text-teks-sekunder">
                                        {[satuan.DefaultJual ? 'Jual' : null, satuan.DefaultBeli ? 'Beli' : null]
                                            .filter(Boolean)
                                            .join(', ') || '—'}
                                    </td>
                                    <td className="px-2 py-2">
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
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        `/kelola/produk/${Produk.Uuid}/satuan/${satuan.Uuid}/barcode-internal`,
                                                        {},
                                                        opsiKirim,
                                                    )
                                                }
                                                className="mt-1 text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                            >
                                                Buat barcode internal {satuan.Simbol}
                                            </button>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="text-keterangan text-teks-sekunder">
                    Barcode internal (EAN-13 berawalan 20) untuk barang tanpa barcode pabrik.
                </p>
            </section>

            {induk ? (
                <section
                    aria-labelledby="judul-varian"
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-varian" className="text-subjudul font-semibold text-teks-utama">
                        Varian ({Varian.length})
                    </h2>
                    {Varian.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">
                            Belum ada varian. Buat varian dari atribut di bawah.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[560px] text-left text-isi">
                                <caption className="sr-only">Daftar varian</caption>
                                <thead className="border-b border-garis text-label text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className="py-2 pr-2 font-semibold">
                                            Varian
                                        </th>
                                        <th scope="col" className="px-2 py-2 font-semibold">
                                            SKU
                                        </th>
                                        <th scope="col" className="px-2 py-2 text-right font-semibold">
                                            Harga dasar
                                        </th>
                                        <th scope="col" className="py-2 pl-2 font-semibold">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Varian.map((varian) => (
                                        <tr key={varian.Uuid} className="border-b border-garis last:border-b-0">
                                            <td className="py-2 pr-2">
                                                <Link
                                                    href={`/kelola/produk/${varian.Uuid}`}
                                                    className="font-semibold text-brand underline"
                                                >
                                                    {varian.Nama}
                                                </Link>
                                                <span className="block text-keterangan text-teks-sekunder">
                                                    {varian.Atribut.map((a) => `${a.Nama}: ${a.Nilai}`).join(' · ')}
                                                </span>
                                            </td>
                                            <td className="px-2 py-2 font-mono text-label">{varian.Sku ?? '—'}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {varian.HargaDasar === null ? (
                                                    <span className="text-teks-sekunder">Belum ada harga</span>
                                                ) : (
                                                    FormatRupiah(varian.HargaDasar)
                                                )}
                                            </td>
                                            <td className="py-2 pl-2">
                                                <LabelStatus
                                                    jenis={varian.Status === 'Aktif' ? 'sukses' : 'netral'}
                                                    teks={varian.Status === 'Aktif' ? 'Aktif' : 'Diarsipkan'}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            ) : null}

            {induk && Izin.Kelola ? (
                <GeneratorVarian
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

            <section
                aria-labelledby="judul-riwayat"
                className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
            >
                <h2 id="judul-riwayat" className="text-subjudul font-semibold text-teks-utama">
                    Riwayat perubahan
                </h2>
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
            </section>
        </TataLetakAplikasi>
    );
}

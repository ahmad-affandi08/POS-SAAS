import { Link, router, usePage } from '@inertiajs/react';
import { useId, useRef, useState, type ChangeEvent, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import { AmbilEkstensiBerkas } from '@/Komponen/Formulir/BidangGambar';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import LangkahImpor, { JenisLabelImpor } from '@/Komponen/Katalog/LangkahImpor';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDaftarImpor, RingkasanImpor } from '@/Tipe/Katalog';
import { FormatBatas } from '@/Tipe/Organisasi';

/** Pemeriksaan berkas sebelum unggah (server tetap memeriksa ulang): ekstensi dan ukuran. */
export function PeriksaBerkasImpor(berkas: File, batas: PropsDaftarImpor['BatasBerkas']): string | null {
    if (!batas.Ekstensi.includes(AmbilEkstensiBerkas(berkas.name))) {
        return `Format ${berkas.name} tidak didukung. Pilih berkas ${batas.Ekstensi.join(' atau ')}.`;
    }

    if (berkas.size > batas.UkuranMaksimalKb * 1024) {
        return `Ukuran ${FormatUkuranBerkas(berkas.size)} melebihi batas ${FormatUkuranBerkas(batas.UkuranMaksimalKb * 1024)}. Bagi berkas menjadi beberapa bagian.`;
    }

    return null;
}

function RingkasHasil(impor: RingkasanImpor): string {
    if (impor.Status === 'Selesai') {
        return `${String(impor.JumlahDibuat)} dibuat, ${String(impor.JumlahDiperbarui)} diperbarui, ${String(impor.JumlahGagal)} gagal`;
    }

    return `${String(impor.JumlahBaris)} baris`;
}

/** F-03 impor produk langkah 1: pilih format sumber, unggah Excel/CSV, dan riwayat impor (BR-03.6). */
export default function HalamanDaftarImpor({ Riwayat, Preset, BatasBerkas, BatasSku, Izin }: PropsDaftarImpor) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const id = useId();
    const masukan = useRef<HTMLInputElement>(null);
    const [sumber, AturSumber] = useState(Preset[0]?.Kode ?? '');
    const [berkas, AturBerkas] = useState<File | null>(null);
    const [galatBerkas, AturGalatBerkas] = useState<string | null>(null);
    const [mengunggah, AturMengunggah] = useState(false);
    const preset = Preset.find((item) => item.Kode === sumber);
    const pesanBerkas = galatBerkas ?? props.errors.Berkas;

    const Pilih = (peristiwa: ChangeEvent<HTMLInputElement>) => {
        const terpilih = peristiwa.target.files?.[0] ?? null;

        if (terpilih === null) {
            return;
        }

        const pesan = PeriksaBerkasImpor(terpilih, BatasBerkas);
        AturGalatBerkas(pesan);
        AturBerkas(pesan === null ? terpilih : null);

        if (pesan !== null) {
            peristiwa.target.value = '';
        }
    };

    const Unggah = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        if (berkas === null) {
            AturGalatBerkas('Pilih berkas Excel atau CSV lebih dulu.');
            masukan.current?.focus();

            return;
        }

        router.post(
            '/kelola/produk/impor',
            { Berkas: berkas, Sumber: sumber },
            { forceFormData: true, onStart: () => AturMengunggah(true), onFinish: () => AturMengunggah(false) },
        );
    };

    return (
        <TataLetakAplikasi judul="Impor produk">
            <p className="text-label">
                <Link href="/kelola/produk" className="font-semibold text-brand underline">
                    Kembali ke daftar produk
                </Link>
            </p>
            <LangkahImpor status={null} />
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="riwayat impor" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={['Berkas']} />

            {Izin.Kelola ? (
                <form
                    onSubmit={Unggah}
                    noValidate
                    aria-labelledby={`${id}-judul`}
                    className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id={`${id}-judul`} className="text-subjudul font-semibold text-teks-utama">
                        Unggah berkas
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-1">
                            <BidangPilihan
                                label="Format berkas dari"
                                nilai={sumber}
                                opsi={Preset.map((item) => ({ Nilai: item.Kode, Label: item.Nama }))}
                                saatBerubah={AturSumber}
                                galat={props.errors.Sumber}
                            />
                            {preset ? <p className="text-keterangan text-teks-sekunder">{preset.Keterangan}</p> : null}
                        </div>
                        <div className="flex flex-col gap-1">
                            <label htmlFor={`${id}-berkas`} className="text-label font-semibold text-teks-utama">
                                Berkas Excel atau CSV
                            </label>
                            <input
                                ref={masukan}
                                id={`${id}-berkas`}
                                type="file"
                                accept={BatasBerkas.Ekstensi.map((item) => `.${item}`).join(',')}
                                onChange={Pilih}
                                aria-invalid={pesanBerkas ? true : undefined}
                                aria-describedby={`${id}-keterangan${pesanBerkas ? ` ${id}-galat` : ''}`}
                                className="text-isi text-teks-utama file:mr-3 file:rounded-kontrol file:border file:border-garis-input file:bg-permukaan file:px-3 file:py-1 file:text-label file:font-semibold"
                            />
                            <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder tabular-nums">
                                Format {BatasBerkas.Ekstensi.join(', ')}, maksimal{' '}
                                {FormatUkuranBerkas(BatasBerkas.UkuranMaksimalKb * 1024)} dan{' '}
                                {BatasBerkas.MaksimalBaris.toLocaleString('id-ID')} baris.
                            </p>
                            <div aria-live="polite">
                                {pesanBerkas ? (
                                    <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                                        {pesanBerkas}
                                    </p>
                                ) : berkas ? (
                                    <p className="text-keterangan text-teks-utama">
                                        {berkas.name} · {FormatUkuranBerkas(berkas.size)}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>
                    {preset?.Asumsi ? (
                        <Pemberitahuan jenis="peringatan" judul="Periksa pemetaan kolom sebelum mengimpor">
                            Nama kolom ekspor {preset.Nama} belum diverifikasi dengan berkas asli. Setelah unggah,
                            cocokkan kembali setiap kolom di langkah Pemetaan kolom.
                        </Pemberitahuan>
                    ) : null}
                    <p className="text-keterangan text-teks-sekunder">
                        Yang diimpor: produk, satuan, barcode, harga dan harga grosir, varian, kategori, dan kelompok
                        pajak. Modifier, resep, daftar harga, stok awal, dan HPP tidak ikut diimpor. Produk terhitung
                        paket saat ini: <span className="tabular-nums">{FormatBatas(BatasSku, 'produk')}</span>.
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <Tombol type="submit" memproses={mengunggah}>
                            Unggah & lanjut ke pemetaan
                        </Tombol>
                        <a
                            href="/kelola/produk/impor/templat?format=xlsx"
                            className="text-label font-semibold text-brand underline"
                        >
                            Unduh templat Excel
                        </a>
                        <a
                            href="/kelola/produk/impor/templat?format=csv"
                            className="text-label font-semibold text-brand underline"
                        >
                            Unduh templat CSV
                        </a>
                    </div>
                </form>
            ) : null}

            <section aria-labelledby="judul-riwayat-impor" className="flex flex-col gap-2">
                <h2 id="judul-riwayat-impor" className="text-subjudul font-semibold text-teks-utama">
                    Riwayat impor
                </h2>
                {Riwayat.Data.length === 0 ? (
                    <KeadaanKosong judul="Belum pernah mengimpor produk. Riwayat disimpan 30 hari." />
                ) : (
                    <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                        <table className="w-full min-w-[720px] text-left text-isi">
                            <caption className="sr-only">Riwayat impor produk</caption>
                            <thead className="border-b border-garis text-label text-teks-sekunder">
                                <tr>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Berkas
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Waktu
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Status
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        Hasil
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {Riwayat.Data.map((impor) => (
                                    <tr key={impor.Uuid} className="border-b border-garis align-top last:border-b-0">
                                        <td className="px-4 py-2">
                                            <Link
                                                href={`/kelola/produk/impor/${impor.Uuid}`}
                                                className="font-semibold break-all text-brand underline"
                                            >
                                                {impor.NamaBerkas}
                                            </Link>
                                            <span className="block text-keterangan text-teks-sekunder">
                                                Format {impor.LabelSumber} · {impor.NamaPengguna ?? 'Sistem'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap text-teks-sekunder">
                                            {FormatTanggalWaktu(impor.DibuatPada)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <LabelStatus
                                                jenis={JenisLabelImpor(impor.Status)}
                                                teks={impor.LabelStatus}
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-teks-sekunder tabular-nums">
                                            {RingkasHasil(impor)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Paginasi
                    alamat="/kelola/produk/impor"
                    saring={{}}
                    halamanSaatIni={Riwayat.HalamanSaatIni}
                    halamanTerakhir={Riwayat.HalamanTerakhir}
                    total={Riwayat.Total}
                    label="Halaman riwayat impor"
                />
            </section>
        </TataLetakAplikasi>
    );
}

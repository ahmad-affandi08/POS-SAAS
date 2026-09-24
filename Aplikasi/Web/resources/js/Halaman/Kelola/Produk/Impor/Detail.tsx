import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KemajuanImpor, { StatusBerjalan } from '@/Komponen/Katalog/KemajuanImpor';
import LangkahImpor, { JenisLabelImpor } from '@/Komponen/Katalog/LangkahImpor';
import PemetaanImpor from '@/Komponen/Katalog/PemetaanImpor';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDetailImpor, RingkasanImpor } from '@/Tipe/Katalog';

/** Preset bawaan yang kolomnya sudah pasti (templat sistem); preset aplikasi lain masih asumsi (DesainF03 H.10). */
export const KodePresetPasti = 'Umum';

/** Tautan laporan impor (xlsx/csv): `jenis=galat` hanya baris bermasalah, `semua` seluruh baris dengan status. */
export function BuatUrlLaporanImpor(uuid: string, jenis: 'galat' | 'semua', format: 'xlsx' | 'csv' = 'xlsx'): string {
    return `/kelola/produk/impor/${uuid}/laporan?jenis=${jenis}&format=${format}`;
}

function Angka({ label, nilai }: { label: string; nilai: number }) {
    return (
        <div className="flex flex-col rounded-kontrol border border-garis px-3 py-2">
            <dt className="text-keterangan text-teks-sekunder">{label}</dt>
            <dd className="text-subjudul font-semibold text-teks-utama tabular-nums">
                {nilai.toLocaleString('id-ID')}
            </dd>
        </div>
    );
}

function TautanLaporan({ impor }: { impor: RingkasanImpor }) {
    return (
        <p className="flex flex-wrap gap-x-4 gap-y-1 text-label">
            {impor.JumlahGalat > 0 || impor.JumlahGagal > 0 ? (
                <>
                    <a href={BuatUrlLaporanImpor(impor.Uuid, 'galat')} className="font-semibold text-brand underline">
                        Unduh laporan galat (Excel)
                    </a>
                    <a
                        href={BuatUrlLaporanImpor(impor.Uuid, 'galat', 'csv')}
                        className="font-semibold text-brand underline"
                    >
                        Unduh laporan galat (CSV)
                    </a>
                </>
            ) : null}
            <a href={BuatUrlLaporanImpor(impor.Uuid, 'semua')} className="font-semibold text-brand underline">
                Unduh laporan semua baris
            </a>
        </p>
    );
}

/** F-03 impor produk langkah 2–5: pemetaan kolom → pratinjau → proses (polling) → laporan galat (BR-03.6). */
export default function HalamanDetailImpor({
    Impor,
    Pemetaan,
    Pratinjau,
    KelompokPajak,
    Jenis,
    Izin,
}: PropsDetailImpor) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [ubahPemetaan, AturUbahPemetaan] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const berjalan = StatusBerjalan.includes(Impor.Status);
    const tampilPemetaan =
        Pemetaan !== null && (Impor.Status === 'MenungguPemetaan' || (Impor.Status === 'Pratinjau' && ubahPemetaan));
    const opsiKirim = {
        preserveScroll: true,
        onStart: () => AturMemproses(true),
        onFinish: () => AturMemproses(false),
    };
    const Kirim = (aksi: 'terapkan' | 'lanjutkan' | 'batalkan') =>
        router.post(`/kelola/produk/impor/${Impor.Uuid}/${aksi}`, {}, opsiKirim);
    const bolehBatal = Izin.Kelola && (Impor.Status === 'MenungguPemetaan' || Impor.Status === 'Pratinjau');

    return (
        <TataLetakAplikasi judul={`Impor ${Impor.NamaBerkas}`}>
            <p className="text-label">
                <Link href="/kelola/produk/impor" className="font-semibold text-brand underline">
                    Kembali ke riwayat impor
                </Link>
            </p>
            <LangkahImpor status={Impor.Status} />
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="impor ini" /> : null}
            <DaftarGalatServer
                galat={props.errors}
                kecuali={
                    tampilPemetaan
                        ? Object.keys(props.errors).filter(
                              (kunci) => kunci.startsWith('Pemetaan.') || kunci.startsWith('Opsi.'),
                          )
                        : []
                }
            />

            <div className="flex flex-wrap items-center gap-2 text-isi text-teks-sekunder">
                <LabelStatus jenis={JenisLabelImpor(Impor.Status)} teks={Impor.LabelStatus} />
                <span>
                    Format {Impor.LabelSumber} · diunggah {FormatTanggalWaktu(Impor.DibuatPada)} oleh{' '}
                    {Impor.NamaPengguna ?? 'Sistem'}
                    {Impor.JumlahBaris > 0 ? ` · ${Impor.JumlahBaris.toLocaleString('id-ID')} baris` : ''}
                </span>
            </div>

            {Impor.BerkasPernahDiimpor ? (
                <Pemberitahuan jenis="peringatan" judul="Berkas yang sama pernah diimpor">
                    Berkas ini pernah diimpor pada {FormatTanggal(Impor.BerkasPernahDiimpor.slice(0, 10))}. Pilih
                    &ldquo;Perbarui produk yang sudah ada&rdquo; agar tidak ada produk ganda.
                </Pemberitahuan>
            ) : null}

            {berjalan ? <KemajuanImpor impor={Impor} /> : null}

            {tampilPemetaan && Izin.Kelola ? (
                <PemetaanImpor
                    uuidImpor={Impor.Uuid}
                    pemetaan={Pemetaan}
                    kelompokPajak={KelompokPajak}
                    jenis={Jenis}
                    bolehUbahHarga={Izin.UbahHarga}
                    presetAsumsi={Impor.Sumber !== KodePresetPasti}
                    galatServer={props.errors}
                    {...(Impor.Status === 'Pratinjau' ? { saatBatal: () => AturUbahPemetaan(false) } : {})}
                />
            ) : null}

            {Impor.Status === 'Pratinjau' && Pratinjau !== null && !ubahPemetaan ? (
                <section
                    aria-labelledby="judul-pratinjau"
                    className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-pratinjau" className="text-subjudul font-semibold text-teks-utama">
                        Pratinjau
                    </h2>
                    <dl className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <Angka label="Baris valid" nilai={Impor.JumlahValid} />
                        <Angka label="Baris bermasalah" nilai={Impor.JumlahGalat} />
                        <Angka label="Produk baru" nilai={Pratinjau.RingkasanAksi.Buat} />
                        <Angka label="Diperbarui" nilai={Pratinjau.RingkasanAksi.Perbarui} />
                        <Angka label="Dilewati" nilai={Pratinjau.RingkasanAksi.Lewati} />
                    </dl>
                    {Pratinjau.DiblokirBatasSku ? (
                        <Pemberitahuan jenis="bahaya" judul="Impor melebihi batas produk paket">
                            {Pratinjau.DiblokirBatasSku}{' '}
                            <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                                Buka menu Langganan
                            </Link>
                        </Pemberitahuan>
                    ) : null}
                    {Pratinjau.Peringatan.length > 0 ? (
                        <Pemberitahuan jenis="info" judul="Catatan">
                            <ul className="list-disc pl-5">
                                {Pratinjau.Peringatan.map((pesan) => (
                                    <li key={pesan}>{pesan}</li>
                                ))}
                            </ul>
                        </Pemberitahuan>
                    ) : null}
                    {Pratinjau.BarisGalat.length > 0 ? (
                        <div className="flex flex-col gap-2">
                            <h3 className="text-label font-semibold text-teks-utama">
                                Baris bermasalah
                                {Impor.JumlahGalat > Pratinjau.BarisGalat.length
                                    ? ` (${String(Pratinjau.BarisGalat.length)} pertama dari ${String(Impor.JumlahGalat)})`
                                    : ''}
                            </h3>
                            <p className="text-keterangan text-teks-sekunder">
                                Baris ini tidak ikut diimpor. Perbaiki di berkas lalu unggah ulang, atau lanjutkan tanpa
                                baris ini.
                            </p>
                            <div className="max-h-96 overflow-auto rounded-kontrol border border-garis">
                                <table className="w-full min-w-[560px] text-left text-label">
                                    <caption className="sr-only">Baris bermasalah</caption>
                                    <thead className="sticky top-0 border-b border-garis bg-permukaan text-teks-sekunder">
                                        <tr>
                                            <th scope="col" className="px-3 py-2 text-right font-semibold">
                                                Baris
                                            </th>
                                            <th scope="col" className="px-3 py-2 font-semibold">
                                                Produk
                                            </th>
                                            <th scope="col" className="px-3 py-2 font-semibold">
                                                Masalah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {Pratinjau.BarisGalat.map((baris) => (
                                            <tr
                                                key={baris.NomorBaris}
                                                className="border-b border-garis align-top last:border-b-0"
                                            >
                                                <td className="px-3 py-2 text-right tabular-nums">
                                                    {baris.NomorBaris}
                                                </td>
                                                <td className="px-3 py-2 break-words">
                                                    {baris.Data.Nama ?? Object.values(baris.Data)[0] ?? '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <ul className="flex flex-col gap-0.5">
                                                        {baris.Galat.map((galat) => (
                                                            <li key={`${galat.Bidang}-${galat.Pesan}`}>
                                                                <span className="font-semibold">{galat.Bidang}:</span>{' '}
                                                                {galat.Pesan}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : null}
                    <TautanLaporan impor={Impor} />
                    {Izin.Kelola ? (
                        <div className="flex flex-wrap gap-2">
                            <Tombol
                                onClick={() => Kirim('terapkan')}
                                memproses={memproses}
                                disabled={Pratinjau.DiblokirBatasSku !== null || Impor.JumlahValid === 0}
                            >
                                Impor {Impor.JumlahValid.toLocaleString('id-ID')} baris valid
                            </Tombol>
                            {Pemetaan !== null ? (
                                <Tombol varian="sekunder" onClick={() => AturUbahPemetaan(true)}>
                                    Ubah pemetaan kolom
                                </Tombol>
                            ) : null}
                        </div>
                    ) : null}
                </section>
            ) : null}

            {Impor.Status === 'Selesai' ? (
                <section
                    aria-labelledby="judul-hasil"
                    className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
                >
                    <h2 id="judul-hasil" className="text-subjudul font-semibold text-teks-utama">
                        Impor selesai
                    </h2>
                    <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <Angka label="Produk dibuat" nilai={Impor.JumlahDibuat} />
                        <Angka label="Produk diperbarui" nilai={Impor.JumlahDiperbarui} />
                        <Angka label="Dilewati" nilai={Impor.JumlahDilewati} />
                        <Angka label="Gagal" nilai={Impor.JumlahGagal} />
                    </dl>
                    {Impor.SelesaiPada ? (
                        <p className="text-keterangan text-teks-sekunder">
                            Selesai {FormatTanggalWaktu(Impor.SelesaiPada)}.
                        </p>
                    ) : null}
                    <TautanLaporan impor={Impor} />
                    <p>
                        <Link href="/kelola/produk" className="font-semibold text-brand underline">
                            Lihat daftar produk
                        </Link>
                    </p>
                </section>
            ) : null}

            {Impor.Status === 'Gagal' ? (
                <Pemberitahuan jenis="bahaya" judul="Impor berhenti">
                    <p>{Impor.PesanGalat ?? 'Terjadi galat saat memproses berkas.'}</p>
                    {Impor.JumlahDiterapkan > 0 ? (
                        <p className="tabular-nums">
                            {Impor.JumlahDiterapkan.toLocaleString('id-ID')} baris sudah diterapkan dan tidak akan
                            diulang.
                        </p>
                    ) : null}
                    {Izin.Kelola && Impor.BolehLanjutkan ? (
                        <div className="mt-2">
                            <Tombol onClick={() => Kirim('lanjutkan')} memproses={memproses}>
                                Lanjutkan impor
                            </Tombol>
                        </div>
                    ) : null}
                    <div className="mt-2">
                        <TautanLaporan impor={Impor} />
                    </div>
                </Pemberitahuan>
            ) : null}

            {Impor.Status === 'Menerapkan' && Impor.BolehLanjutkan && Izin.Kelola ? (
                <Pemberitahuan jenis="peringatan" judul="Impor tampak terhenti">
                    <p>
                        Tidak ada kemajuan lebih dari 10 menit. Lanjutkan untuk memproses baris yang belum diterapkan.
                    </p>
                    <div className="mt-2">
                        <Tombol onClick={() => Kirim('lanjutkan')} memproses={memproses}>
                            Lanjutkan impor
                        </Tombol>
                    </div>
                </Pemberitahuan>
            ) : null}

            {Impor.Status === 'Dibatalkan' ? (
                <Pemberitahuan jenis="info" judul="Impor dibatalkan">
                    Tidak ada produk yang diubah.{' '}
                    <Link href="/kelola/produk/impor" className="font-semibold text-brand underline">
                        Unggah berkas lain
                    </Link>
                </Pemberitahuan>
            ) : null}

            {bolehBatal ? (
                <div>
                    <Tombol varian="bahaya" onClick={() => Kirim('batalkan')} disabled={memproses}>
                        Batalkan impor
                    </Tombol>
                </div>
            ) : null}
        </TataLetakAplikasi>
    );
}

import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { FormatDurasi, FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type CatatanBackup = {
    Uuid: string;
    Jenis: 'Backup' | 'UjiRestore';
    LabelJenis: string;
    Hasil: 'Berhasil' | 'Gagal';
    SelesaiPada: string;
    UkuranByte: number | null;
    Lokasi: string | null;
    Keterangan: string | null;
    Sumber: 'Skrip' | 'Manual';
    DicatatOleh?: string;
};

type Alert = {
    Id: number;
    Label: string;
    Tingkat: 'Kritis' | 'Peringatan';
    Pesan: string;
    MulaiPada: string;
    SelesaiPada: string | null;
    EmailTerkirimPada: string | null;
};

type Dasbor = {
    Penjadwal: { TerakhirPada: string | null; UmurDetik: number | null; Sehat: boolean; BatasMenit: number };
    Antrean: {
        PerAntrean: { Antrean: string; Menunggu: number; Diproses: number; UmurTertuaDetik: number | null }[];
        UmurTertuaDetik: number | null;
        Sehat: boolean;
        BatasMenit: number;
    };
    TugasGagal: {
        Total: number;
        Data: { Uuid: string; Antrean: string; NamaTugas: string; RingkasanGalat: string; GagalPada: string }[];
    };
    Backup: {
        BackupTerakhir: CatatanBackup | null;
        UjiRestoreTerakhir: CatatanBackup | null;
        Sehat: boolean;
        BatasJam: number;
        Riwayat: CatatanBackup[];
    };
    Alert: { Aktif: Alert[]; Riwayat: Alert[] };
    Kesehatan: {
        PeringatanIntegrasi: string[];
        UkuranDatabaseByte: number | null;
        Disk: { SisaByte: number; TotalByte: number } | null;
    };
};

/** Dasbor operasional dasar (P-11): scheduler, antrean, job gagal, backup, alert, kesehatan server. */
export default function DasborOperasional({ Dasbor }: { Dasbor: Dasbor }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.OperasionalKelola);
    const { Penjadwal, Antrean, TugasGagal, Backup, Alert, Kesehatan } = Dasbor;

    return (
        <TataLetakPengelola judul="Operasional">
            <div className="grid gap-4 md:grid-cols-3">
                <Ringkasan
                    judul="Scheduler"
                    sehat={Penjadwal.Sehat}
                    nilai={
                        Penjadwal.TerakhirPada
                            ? `Detak ${FormatDurasi(Penjadwal.UmurDetik)} lalu`
                            : 'Belum pernah berdetak'
                    }
                    keterangan={`Alert bila tidak berdetak lebih dari ${String(Penjadwal.BatasMenit)} menit.`}
                />
                <Ringkasan
                    judul="Antrean"
                    sehat={Antrean.Sehat}
                    nilai={
                        Antrean.UmurTertuaDetik === null
                            ? 'Kosong'
                            : `Job tertua ${FormatDurasi(Antrean.UmurTertuaDetik)}`
                    }
                    keterangan={`Alert bila job tertua lebih dari ${String(Antrean.BatasMenit)} menit.`}
                />
                <Ringkasan
                    judul="Backup"
                    sehat={Backup.Sehat}
                    nilai={
                        Backup.BackupTerakhir
                            ? `Berhasil ${FormatTanggalWaktu(Backup.BackupTerakhir.SelesaiPada)}`
                            : 'Belum ada backup berhasil'
                    }
                    keterangan={`Alert bila backup berhasil terakhir lebih dari ${String(Backup.BatasJam)} jam.`}
                />
            </div>

            <Bagian judul="Alert aktif">
                {Alert.Aktif.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada alert aktif.</p>
                ) : (
                    <DaftarAlert alert={Alert.Aktif} />
                )}
            </Bagian>

            <Bagian judul="Antrean per nama">
                {Antrean.PerAntrean.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada job di antrean.</p>
                ) : (
                    <Tabel kolom={['Antrean', 'Menunggu', 'Diproses', 'Umur tertua']}>
                        {Antrean.PerAntrean.map((baris) => (
                            <tr key={baris.Antrean} className="border-b border-garis last:border-b-0">
                                <td className="px-3 py-2 font-mono text-label">{baris.Antrean}</td>
                                <td className="px-3 py-2 text-right tabular-nums">{baris.Menunggu}</td>
                                <td className="px-3 py-2 text-right tabular-nums">{baris.Diproses}</td>
                                <td className="px-3 py-2">{FormatDurasi(baris.UmurTertuaDetik)}</td>
                            </tr>
                        ))}
                    </Tabel>
                )}
            </Bagian>

            <Bagian judul={`Job gagal (${String(TugasGagal.Total)})`}>
                {TugasGagal.Data.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada job gagal.</p>
                ) : (
                    <Tabel kolom={['Waktu gagal', 'Job', 'Antrean', 'Galat']}>
                        {TugasGagal.Data.map((tugas) => (
                            <tr key={tugas.Uuid} className="border-b border-garis align-top last:border-b-0">
                                <td className="whitespace-nowrap px-3 py-2 text-teks-sekunder">
                                    {FormatTanggalWaktu(tugas.GagalPada)}
                                </td>
                                <td className="px-3 py-2 font-mono text-label">
                                    <Link
                                        href={`/operasional/tugas-gagal/${tugas.Uuid}`}
                                        className="break-all font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        {tugas.NamaTugas}
                                    </Link>
                                </td>
                                <td className="px-3 py-2 font-mono text-label">{tugas.Antrean}</td>
                                <td className="break-all px-3 py-2 text-keterangan text-teks-sekunder">
                                    {tugas.RingkasanGalat}
                                </td>
                            </tr>
                        ))}
                    </Tabel>
                )}
            </Bagian>

            <Bagian judul="Backup & uji restore">
                <dl className="grid gap-2 text-label sm:grid-cols-2">
                    <div>
                        <dt className="text-teks-sekunder">Backup berhasil terakhir</dt>
                        <dd className="text-teks-utama">
                            {Backup.BackupTerakhir
                                ? `${FormatTanggalWaktu(Backup.BackupTerakhir.SelesaiPada)} · ${FormatUkuranBerkas(Backup.BackupTerakhir.UkuranByte)}`
                                : 'Belum ada'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-teks-sekunder">Uji restore terakhir</dt>
                        <dd className="text-teks-utama">
                            {Backup.UjiRestoreTerakhir
                                ? `${Backup.UjiRestoreTerakhir.Hasil} · ${FormatTanggalWaktu(Backup.UjiRestoreTerakhir.SelesaiPada)}`
                                : 'Belum pernah dicatat (wajib tiap bulan, §14.5)'}
                        </dd>
                    </div>
                </dl>
                {Backup.Riwayat.length > 0 ? (
                    <Tabel kolom={['Selesai', 'Jenis', 'Hasil', 'Ukuran', 'Lokasi & keterangan', 'Dicatat oleh']}>
                        {Backup.Riwayat.map((catatan) => (
                            <tr key={catatan.Uuid} className="border-b border-garis align-top last:border-b-0">
                                <td className="whitespace-nowrap px-3 py-2">
                                    {FormatTanggalWaktu(catatan.SelesaiPada)}
                                </td>
                                <td className="px-3 py-2">{catatan.LabelJenis}</td>
                                <td className="px-3 py-2">
                                    <LabelStatus
                                        jenis={catatan.Hasil === 'Berhasil' ? 'sukses' : 'bahaya'}
                                        teks={catatan.Hasil}
                                    />
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {FormatUkuranBerkas(catatan.UkuranByte)}
                                </td>
                                <td className="break-all px-3 py-2 text-keterangan text-teks-sekunder">
                                    {catatan.Lokasi ? <span className="block font-mono">{catatan.Lokasi}</span> : null}
                                    {catatan.Keterangan}
                                </td>
                                <td className="px-3 py-2">{catatan.DicatatOleh}</td>
                            </tr>
                        ))}
                    </Tabel>
                ) : null}
                {bolehKelola ? <FormCatatBackup /> : null}
            </Bagian>

            <Bagian judul="Kesehatan server">
                <dl className="grid gap-2 text-label sm:grid-cols-3">
                    <div>
                        <dt className="text-teks-sekunder">Ukuran database</dt>
                        <dd className="text-teks-utama">{FormatUkuranBerkas(Kesehatan.UkuranDatabaseByte)}</dd>
                    </div>
                    <div>
                        <dt className="text-teks-sekunder">Ruang disk tersisa</dt>
                        <dd className="text-teks-utama">
                            {Kesehatan.Disk
                                ? `${FormatUkuranBerkas(Kesehatan.Disk.SisaByte)} dari ${FormatUkuranBerkas(Kesehatan.Disk.TotalByte)}`
                                : 'Tidak tersedia di hosting ini'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-teks-sekunder">Integrasi (P-05)</dt>
                        <dd className="text-teks-utama">
                            {Kesehatan.PeringatanIntegrasi.length === 0
                                ? 'Tidak ada masalah'
                                : `${String(Kesehatan.PeringatanIntegrasi.length)} peringatan`}
                            {PunyaIzin(props.Pengguna, IzinPengelola.IntegrasiLihat) ? (
                                <>
                                    {' · '}
                                    <Link href="/integrasi" className="font-semibold text-brand underline">
                                        Buka integrasi
                                    </Link>
                                </>
                            ) : null}
                        </dd>
                    </div>
                </dl>
            </Bagian>

            <Bagian judul="Riwayat alert">
                {Alert.Riwayat.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Belum ada alert yang selesai.</p>
                ) : (
                    <DaftarAlert alert={Alert.Riwayat} />
                )}
            </Bagian>
        </TataLetakPengelola>
    );
}

function Ringkasan({
    judul,
    sehat,
    nilai,
    keterangan,
}: {
    judul: string;
    sehat: boolean;
    nilai: string;
    keterangan: string;
}) {
    return (
        <section className="flex flex-col gap-1 rounded-panel border border-garis bg-permukaan p-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
                <LabelStatus jenis={sehat ? 'sukses' : 'bahaya'} teks={sehat ? 'Normal' : 'Bermasalah'} />
            </div>
            <p className="text-isi text-teks-utama">{nilai}</p>
            <p className="text-keterangan text-teks-sekunder">{keterangan}</p>
        </section>
    );
}

function Bagian({ judul, children }: { judul: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4">
            <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
            {children}
        </section>
    );
}

function Tabel({ kolom, children }: { kolom: string[]; children: ReactNode }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-isi">
                <thead className="border-b border-garis text-label text-teks-sekunder">
                    <tr>
                        {kolom.map((nama) => (
                            <th key={nama} scope="col" className="px-3 py-2 font-semibold">
                                {nama}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

function DaftarAlert({ alert }: { alert: Alert[] }) {
    return (
        <ul className="flex flex-col gap-2">
            {alert.map((baris) => (
                <li key={baris.Id} className="flex flex-col gap-1 border-b border-garis pb-2 last:border-b-0">
                    <p className="flex flex-wrap items-center gap-2 text-label">
                        <LabelStatus
                            jenis={baris.Tingkat === 'Kritis' ? 'bahaya' : 'peringatan'}
                            teks={baris.Tingkat}
                        />
                        <span className="font-semibold text-teks-utama">{baris.Label}</span>
                        <span className="text-teks-sekunder">
                            {FormatTanggalWaktu(baris.MulaiPada)}
                            {baris.SelesaiPada ? ` – ${FormatTanggalWaktu(baris.SelesaiPada)}` : ''}
                        </span>
                    </p>
                    <p className="text-isi text-teks-utama">{baris.Pesan}</p>
                    <p className="text-keterangan text-teks-sekunder">
                        {baris.EmailTerkirimPada
                            ? `Email ke Teknis terkirim ${FormatTanggalWaktu(baris.EmailTerkirimPada)}`
                            : 'Email belum terkirim (dicoba lagi pada pemeriksaan berikutnya)'}
                    </p>
                </li>
            ))}
        </ul>
    );
}

function FormCatatBackup() {
    const formulir = useForm({
        Jenis: 'UjiRestore',
        Hasil: 'Berhasil',
        SelesaiPada: '',
        UkuranMb: '',
        Lokasi: '',
        Keterangan: '',
    });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/operasional/backup', { preserveScroll: true, onSuccess: () => formulir.reset() });
    };

    return (
        <form onSubmit={Kirim} className="grid gap-3 border-t border-garis pt-3 sm:grid-cols-3">
            <p className="text-label font-semibold text-teks-utama sm:col-span-3">Catat hasil secara manual</p>
            <BidangPilihan
                label="Jenis"
                nilai={formulir.data.Jenis}
                opsi={[
                    { Nilai: 'UjiRestore', Label: 'Uji restore' },
                    { Nilai: 'Backup', Label: 'Backup' },
                ]}
                saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                galat={formulir.errors.Jenis}
            />
            <BidangPilihan
                label="Hasil"
                nilai={formulir.data.Hasil}
                opsi={[
                    { Nilai: 'Berhasil', Label: 'Berhasil' },
                    { Nilai: 'Gagal', Label: 'Gagal' },
                ]}
                saatBerubah={(nilai) => formulir.setData('Hasil', nilai)}
                galat={formulir.errors.Hasil}
            />
            <BidangWaktu
                label="Selesai pada (WIB)"
                nilai={formulir.data.SelesaiPada}
                saatBerubah={(nilai) => formulir.setData('SelesaiPada', nilai)}
                galat={formulir.errors.SelesaiPada}
            />
            <BidangTeks
                label="Ukuran (MB)"
                nilai={formulir.data.UkuranMb}
                inputMode="numeric"
                saatBerubah={(nilai) => formulir.setData('UkuranMb', nilai)}
                galat={formulir.errors.UkuranMb}
            />
            <BidangTeks
                label="Lokasi (path, tanpa kredensial)"
                nilai={formulir.data.Lokasi}
                kode
                saatBerubah={(nilai) => formulir.setData('Lokasi', nilai)}
                galat={formulir.errors.Lokasi}
            />
            <BidangTeks
                label="Keterangan"
                nilai={formulir.data.Keterangan}
                saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                galat={formulir.errors.Keterangan}
            />
            <div className="sm:col-span-3">
                <Tombol type="submit" varian="sekunder" memproses={formulir.processing}>
                    Simpan catatan
                </Tombol>
            </div>
        </form>
    );
}

function BidangWaktu({
    label,
    nilai,
    saatBerubah,
    galat,
}: {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
}) {
    return (
        <label className="flex flex-col gap-1 text-label font-semibold text-teks-utama">
            {label}
            <input
                type="datetime-local"
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                className={`h-10 rounded-kontrol border bg-permukaan px-3 text-isi font-normal text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                }`}
            />
            {galat ? <span className="text-keterangan font-semibold text-bahaya">{galat}</span> : null}
        </label>
    );
}

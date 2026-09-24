import { Link, useForm, usePage } from '@inertiajs/react';
import { useId, type FormEvent, type ReactNode } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import {
    Alert as KotakPeringatan,
    AlertDescription as IsiPeringatan,
    AlertTitle as JudulPeringatan,
} from '@/Komponen/Ui/alert';
import { Button } from '@/Komponen/Ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Input } from '@/Komponen/Ui/input';
import { Label } from '@/Komponen/Ui/label';
import { Progress } from '@/Komponen/Ui/progress';
import { Separator } from '@/Komponen/Ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
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

/** Persentase disk terpakai (bilangan bulat 0–100) untuk indikator; bukan uang/kuantitas. */
function HitungPersenDiskTerpakai(disk: { SisaByte: number; TotalByte: number }): number {
    if (disk.TotalByte <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round(((disk.TotalByte - disk.SisaByte) / disk.TotalByte) * 100)));
}

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
                    <DaftarAlertAktif alert={Alert.Aktif} />
                )}
            </Bagian>

            <Bagian judul="Antrean per nama">
                {Antrean.PerAntrean.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada job di antrean.</p>
                ) : (
                    <Tabel kolom={['Antrean', 'Menunggu', 'Diproses', 'Umur tertua']}>
                        {Antrean.PerAntrean.map((baris) => (
                            <TableRow key={baris.Antrean}>
                                <TableCell className="font-mono text-label">{baris.Antrean}</TableCell>
                                <TableCell className="text-right tabular-nums">{baris.Menunggu}</TableCell>
                                <TableCell className="text-right tabular-nums">{baris.Diproses}</TableCell>
                                <TableCell>{FormatDurasi(baris.UmurTertuaDetik)}</TableCell>
                            </TableRow>
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
                            <TableRow key={tugas.Uuid}>
                                <TableCell className="whitespace-nowrap text-teks-sekunder">
                                    {FormatTanggalWaktu(tugas.GagalPada)}
                                </TableCell>
                                <TableCell className="font-mono text-label">
                                    <Button
                                        asChild
                                        variant="link"
                                        className="h-auto p-0 font-mono text-label font-semibold break-all whitespace-normal"
                                    >
                                        <Link href={`/operasional/tugas-gagal/${tugas.Uuid}`}>{tugas.NamaTugas}</Link>
                                    </Button>
                                </TableCell>
                                <TableCell className="font-mono text-label">{tugas.Antrean}</TableCell>
                                <TableCell className="text-keterangan break-all text-teks-sekunder">
                                    {tugas.RingkasanGalat}
                                </TableCell>
                            </TableRow>
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
                            <TableRow key={catatan.Uuid}>
                                <TableCell className="whitespace-nowrap">
                                    {FormatTanggalWaktu(catatan.SelesaiPada)}
                                </TableCell>
                                <TableCell>{catatan.LabelJenis}</TableCell>
                                <TableCell>
                                    <LabelStatus
                                        jenis={catatan.Hasil === 'Berhasil' ? 'sukses' : 'bahaya'}
                                        teks={catatan.Hasil}
                                    />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {FormatUkuranBerkas(catatan.UkuranByte)}
                                </TableCell>
                                <TableCell className="text-keterangan break-all text-teks-sekunder">
                                    {catatan.Lokasi ? <span className="block font-mono">{catatan.Lokasi}</span> : null}
                                    {catatan.Keterangan}
                                </TableCell>
                                <TableCell>{catatan.DicatatOleh}</TableCell>
                            </TableRow>
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
                        <dd className="flex flex-col gap-1 text-teks-utama">
                            {Kesehatan.Disk
                                ? `${FormatUkuranBerkas(Kesehatan.Disk.SisaByte)} dari ${FormatUkuranBerkas(Kesehatan.Disk.TotalByte)}`
                                : 'Tidak tersedia di hosting ini'}
                            {Kesehatan.Disk ? (
                                <Progress
                                    value={HitungPersenDiskTerpakai(Kesehatan.Disk)}
                                    aria-label={`Disk terpakai ${String(HitungPersenDiskTerpakai(Kesehatan.Disk))}%`}
                                    className="max-w-60"
                                />
                            ) : null}
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
                                    <Button asChild variant="link" className="h-auto p-0 text-label font-semibold">
                                        <Link href="/integrasi">Buka integrasi</Link>
                                    </Button>
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
        <Card className="gap-1 py-4">
            <CardHeader className="px-4">
                <CardTitle>
                    <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
                </CardTitle>
                <CardAction>
                    <LabelStatus jenis={sehat ? 'sukses' : 'bahaya'} teks={sehat ? 'Normal' : 'Bermasalah'} />
                </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-4">
                <p className="text-isi text-teks-utama">{nilai}</p>
                <CardDescription className="text-keterangan text-teks-sekunder">{keterangan}</CardDescription>
            </CardContent>
        </Card>
    );
}

function Bagian({ judul, children }: { judul: string; children: ReactNode }) {
    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle>
                    <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3 px-4">{children}</CardContent>
        </Card>
    );
}

function Tabel({ kolom, children }: { kolom: string[]; children: ReactNode }) {
    return (
        <Table className="min-w-[640px] text-isi [&_td]:px-3 [&_td]:py-2 [&_td]:align-top [&_td]:whitespace-normal">
            <TableHeader>
                <TableRow>
                    {kolom.map((nama) => (
                        <TableHead key={nama} scope="col" className="px-3 text-label font-semibold text-teks-sekunder">
                            {nama}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>{children}</TableBody>
        </Table>
    );
}

function TeksWaktuAlert({ baris }: { baris: Alert }) {
    return (
        <>
            {FormatTanggalWaktu(baris.MulaiPada)}
            {baris.SelesaiPada ? ` – ${FormatTanggalWaktu(baris.SelesaiPada)}` : ''}
        </>
    );
}

function TeksEmailAlert({ baris }: { baris: Alert }) {
    return baris.EmailTerkirimPada
        ? `Email ke Teknis terkirim ${FormatTanggalWaktu(baris.EmailTerkirimPada)}`
        : 'Email belum terkirim (dicoba lagi pada pemeriksaan berikutnya)';
}

/** Alert yang masih terbuka: kotak Alert per kejadian, tingkat selalu tertulis (tidak hanya warna). */
function DaftarAlertAktif({ alert }: { alert: Alert[] }) {
    return (
        <ul className="flex flex-col gap-2">
            {alert.map((baris) => (
                <li key={baris.Id}>
                    <KotakPeringatan
                        variant={baris.Tingkat === 'Kritis' ? 'destructive' : 'default'}
                        className={cn(baris.Tingkat === 'Kritis' ? 'border-bahaya' : 'border-peringatan')}
                    >
                        <JudulPeringatan className="flex flex-wrap items-center gap-2 text-label">
                            <LabelStatus
                                jenis={baris.Tingkat === 'Kritis' ? 'bahaya' : 'peringatan'}
                                teks={baris.Tingkat}
                            />
                            <span className="font-semibold text-teks-utama">{baris.Label}</span>
                            <span className="font-normal text-teks-sekunder">
                                <TeksWaktuAlert baris={baris} />
                            </span>
                        </JudulPeringatan>
                        <IsiPeringatan>
                            <p className="text-isi text-teks-utama">{baris.Pesan}</p>
                            <p className="text-keterangan text-teks-sekunder">
                                <TeksEmailAlert baris={baris} />
                            </p>
                        </IsiPeringatan>
                    </KotakPeringatan>
                </li>
            ))}
        </ul>
    );
}

function DaftarAlert({ alert }: { alert: Alert[] }) {
    return (
        <ul className="flex flex-col gap-2">
            {alert.map((baris, indeks) => (
                <li key={baris.Id} className="flex flex-col gap-1">
                    {indeks > 0 ? <Separator className="mb-1" /> : null}
                    <p className="flex flex-wrap items-center gap-2 text-label">
                        <LabelStatus
                            jenis={baris.Tingkat === 'Kritis' ? 'bahaya' : 'peringatan'}
                            teks={baris.Tingkat}
                        />
                        <span className="font-semibold text-teks-utama">{baris.Label}</span>
                        <span className="text-teks-sekunder">
                            <TeksWaktuAlert baris={baris} />
                        </span>
                    </p>
                    <p className="text-isi text-teks-utama">{baris.Pesan}</p>
                    <p className="text-keterangan text-teks-sekunder">
                        <TeksEmailAlert baris={baris} />
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
        <form onSubmit={Kirim} className="grid gap-3 sm:grid-cols-3">
            <Separator className="sm:col-span-3" />
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

/** Tanggal & jam lokal (`datetime-local`); nilai string dikirim apa adanya. */
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
    const idBidang = useId();
    const idGalat = `${idBidang}-galat`;

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={idBidang} className="text-label font-semibold text-teks-utama">
                {label}
            </Label>
            <Input
                id={idBidang}
                type="datetime-local"
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={galat ? idGalat : undefined}
                className="h-10 text-isi"
            />
            {galat ? (
                <span id={idGalat} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </span>
            ) : null}
        </div>
    );
}

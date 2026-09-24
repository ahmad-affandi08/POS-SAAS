import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import BidangTanggal from '@/Komponen/Pengelola/BidangTanggal';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import DialogTinjauan from '@/Komponen/Tindakan/DialogTinjauan';
import PanelTabel from '@/Komponen/Pengelola/PanelTabel';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type Pilihan, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type HariLibur = {
    Uuid: string;
    Tanggal: string;
    Nama: string;
    Jenis: string;
    Status: 'Draf' | 'MenungguTinjauan' | 'Terbit' | 'Dibatalkan';
    NomorDasarHukum: string | null;
    PembatalanMenunggu: boolean;
    AlasanPembatalan: string | null;
    IdPengajuBatal: number | null;
    DibatalkanPada: string | null;
};

type PropsHariLibur = {
    Tahun: number;
    HariLibur: HariLibur[];
    IdPengajuMenunggu: number[];
    PeninjauMenunggu: number[];
    IdPengguna: number;
    PilihanJenis: Pilihan[];
};

const labelStatus = {
    Draf: { jenis: 'netral', teks: 'Draf' },
    MenungguTinjauan: { jenis: 'peringatan', teks: 'Menunggu tinjauan' },
    Terbit: { jenis: 'sukses', teks: 'Terbit' },
    Dibatalkan: { jenis: 'bahaya', teks: 'Dibatalkan' },
} as const;

const formatTanggal = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: 'UTC',
});

/** Hari libur nasional & cuti bersama per tahun (P-02). Wajib terbit paling lambat 1 Desember (BR-P02.4). */
export default function HalamanHariLibur({
    Tahun,
    HariLibur,
    IdPengajuMenunggu,
    PeninjauMenunggu,
    IdPengguna,
    PilihanJenis,
}: PropsHariLibur) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehAjukan = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiHariLiburAjukan);
    const bolehSetujui = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiHariLiburSetujui);
    const [sunting, AturSunting] = useState<HariLibur | 'baru' | null>(null);
    const [meninjau, AturMeninjau] = useState(false);
    const [pembatalan, AturPembatalan] = useState<{ jenis: 'ajukan' | 'tinjau'; hari: HariLibur } | null>(null);
    const labelJenis = new Map(PilihanJenis.map((item) => [item.Nilai, item.Label]));
    const adaDraf = HariLibur.some((hari) => hari.Status === 'Draf');
    const adaMenunggu = HariLibur.some((hari) => hari.Status === 'MenungguTinjauan');
    const bisaTinjau =
        bolehSetujui &&
        adaMenunggu &&
        !IdPengajuMenunggu.includes(IdPengguna) &&
        !PeninjauMenunggu.includes(IdPengguna);

    const PilihTahun = (tahun: string) =>
        router.get('/referensi/hari-libur', { saring: { Tahun: tahun } }, { preserveState: false });
    const AjukanTahun = () => router.post(`/referensi/hari-libur/tahun/${Tahun}/ajukan`, {}, { preserveScroll: true });
    const HapusDraf = (hari: HariLibur) =>
        router.delete(`/referensi/hari-libur/${hari.Uuid}`, { preserveScroll: true });
    const pilihanTahun = [Tahun - 1, Tahun, Tahun + 1].map((tahun) => ({ Nilai: String(tahun), Label: String(tahun) }));

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehAjukan && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah hari libur</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}

            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="w-40">
                    <BidangPilihan label="Tahun" nilai={String(Tahun)} opsi={pilihanTahun} saatBerubah={PilihTahun} />
                </div>
                <div className="flex gap-2">
                    {bolehAjukan && adaDraf ? <Tombol onClick={AjukanTahun}>Ajukan semua draf {Tahun}</Tombol> : null}
                    {bisaTinjau && !meninjau ? (
                        <Tombol onClick={() => AturMeninjau(true)}>Tinjau hari libur {Tahun}</Tombol>
                    ) : null}
                </div>
            </div>

            {sunting !== null ? (
                <FormHariLibur
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    hariLibur={sunting === 'baru' ? null : sunting}
                    tahun={Tahun}
                    pilihanJenis={PilihanJenis}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {pembatalan !== null ? (
                <FormPembatalan
                    key={`${pembatalan.jenis}-${pembatalan.hari.Uuid}`}
                    jenis={pembatalan.jenis}
                    hari={pembatalan.hari}
                    saatSelesai={() => AturPembatalan(null)}
                />
            ) : null}
            {meninjau ? (
                <FormTinjauTahun
                    tahun={Tahun}
                    jumlah={HariLibur.filter((hari) => hari.Status === 'MenungguTinjauan').length}
                    saatSelesai={() => AturMeninjau(false)}
                />
            ) : null}

            {HariLibur.length === 0 ? (
                <Pemberitahuan jenis="peringatan" judul={`Belum ada hari libur ${Tahun}`}>
                    Masukkan libur nasional & cuti bersama sesuai SKB terbaru. Hari libur tahun berikutnya wajib terbit
                    paling lambat 1 Desember.
                </Pemberitahuan>
            ) : (
                <PanelTabel keterangan={`Hari libur tahun ${Tahun}`}>
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Tanggal</TableHead>
                            <TableHead scope="col">Nama</TableHead>
                            <TableHead scope="col">Jenis</TableHead>
                            <TableHead scope="col">Dasar hukum</TableHead>
                            <TableHead scope="col">Status</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {HariLibur.map((hari) => (
                            <TableRow key={hari.Uuid}>
                                <TableCell className="whitespace-nowrap text-teks-utama">
                                    {formatTanggal.format(new Date(`${hari.Tanggal}T00:00:00Z`))}
                                </TableCell>
                                <TableCell className="text-teks-utama">{hari.Nama}</TableCell>
                                <TableCell className="text-teks-sekunder">
                                    {labelJenis.get(hari.Jenis) ?? hari.Jenis}
                                </TableCell>
                                <TableCell className="text-teks-sekunder">{hari.NomorDasarHukum ?? '—'}</TableCell>
                                <TableCell>
                                    <LabelStatus
                                        jenis={labelStatus[hari.Status].jenis}
                                        teks={labelStatus[hari.Status].teks}
                                    />
                                    {hari.PembatalanMenunggu ? (
                                        <p className="mt-1 text-keterangan text-peringatan">
                                            Pembatalan menunggu tinjauan: {hari.AlasanPembatalan}
                                        </p>
                                    ) : null}
                                </TableCell>
                                <TableCell>
                                    {bolehAjukan && hari.Status === 'Draf' ? (
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" onClick={() => AturSunting(hari)}>
                                                Ubah
                                            </Button>
                                            <Button variant="destructive" size="sm" onClick={() => HapusDraf(hari)}>
                                                Hapus
                                            </Button>
                                        </div>
                                    ) : null}
                                    {bolehAjukan && hari.Status === 'Terbit' && !hari.PembatalanMenunggu ? (
                                        <div className="flex justify-end">
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => AturPembatalan({ jenis: 'ajukan', hari })}
                                            >
                                                Ajukan pembatalan
                                            </Button>
                                        </div>
                                    ) : null}
                                    {bolehSetujui && hari.PembatalanMenunggu && hari.IdPengajuBatal !== IdPengguna ? (
                                        <div className="flex justify-end">
                                            <Button size="sm" onClick={() => AturPembatalan({ jenis: 'tinjau', hari })}>
                                                Tinjau pembatalan
                                            </Button>
                                        </div>
                                    ) : null}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </PanelTabel>
            )}
        </TataLetakPengelola>
    );
}

type PropsFormHariLibur = {
    hariLibur: HariLibur | null;
    tahun: number;
    pilihanJenis: Pilihan[];
    saatSelesai: () => void;
};

function FormHariLibur({ hariLibur, tahun, pilihanJenis, saatSelesai }: PropsFormHariLibur) {
    const formulir = useForm({
        Tanggal: hariLibur?.Tanggal ?? `${tahun}-`,
        Nama: hariLibur?.Nama ?? '',
        Jenis: hariLibur?.Jenis ?? 'Nasional',
        NomorDasarHukum: hariLibur?.NomorDasarHukum ?? '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (hariLibur === null) {
            formulir.post('/referensi/hari-libur', opsi);
        } else {
            formulir.put(`/referensi/hari-libur/${hariLibur.Uuid}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={hariLibur === null ? 'Tambah hari libur' : `Ubah ${hariLibur.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTanggal
                    label="Tanggal (TTTT-BB-HH)"
                    nilai={formulir.data.Tanggal}
                    saatBerubah={(nilai) => formulir.setData('Tanggal', nilai)}
                    galat={formulir.errors.Tanggal}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangPilihan
                    label="Jenis"
                    nilai={formulir.data.Jenis}
                    opsi={pilihanJenis}
                    saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                    galat={formulir.errors.Jenis}
                />
                <BidangTeks
                    label="Nomor dasar hukum"
                    keterangan="Misal SKB 3 Menteri. Wajib sebelum diajukan."
                    nilai={formulir.data.NomorDasarHukum}
                    saatBerubah={(nilai) => formulir.setData('NomorDasarHukum', nilai)}
                    galat={formulir.errors.NomorDasarHukum}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan draf
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

function FormTinjauTahun({ tahun, jumlah, saatSelesai }: { tahun: number; jumlah: number; saatSelesai: () => void }) {
    const formulir = useForm({ Keputusan: 'Setuju', Catatan: '' });

    const Kirim = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ ...data, Keputusan: keputusan }));
        formulir.post(`/referensi/hari-libur/tahun/${tahun}/tinjau`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogTinjauan
            judul={`Tinjau ${jumlah} hari libur tahun ${tahun}`}
            deskripsi="Cocokkan setiap tanggal dengan SKB. Setelah terbit, data tidak bisa diubah."
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
            aksi={
                <>
                    <Tombol memproses={formulir.processing} onClick={() => Kirim('Setuju')}>
                        Terbitkan hari libur
                    </Tombol>
                    <Tombol varian="bahaya" disabled={formulir.processing} onClick={() => Kirim('Tolak')}>
                        Tolak pengajuan
                    </Tombol>
                </>
            }
        >
            <BidangTeks
                label="Catatan (wajib bila menolak)"
                nilai={formulir.data.Catatan}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
                maxLength={500}
            />
        </DialogTinjauan>
    );
}

type PropsFormPembatalan = { jenis: 'ajukan' | 'tinjau'; hari: HariLibur; saatSelesai: () => void };

function FormPembatalan({ jenis, hari, saatSelesai }: PropsFormPembatalan) {
    const formulir = useForm({ Alasan: '', Keputusan: 'Setuju', Catatan: '' });

    const Ajukan = () =>
        formulir.post(`/referensi/hari-libur/${hari.Uuid}/pembatalan`, {
            preserveScroll: true,
            onSuccess: saatSelesai,
        });
    const Tinjau = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ Keputusan: keputusan, Catatan: data.Catatan }));
        formulir.post(`/referensi/hari-libur/${hari.Uuid}/pembatalan/tinjau`, {
            preserveScroll: true,
            onSuccess: saatSelesai,
        });
    };

    const galatUmum = (formulir.errors as Record<string, string | undefined>).Umum;

    if (jenis === 'ajukan') {
        // Mengajukan butuh alasan yang langsung diketik: dialog biasa agar fokus awal di isian alasan.
        return (
            <DialogFormulir
                judul={`Ajukan pembatalan ${hari.Nama}`}
                keterangan="Hari libur tetap berlaku sampai pembatalan disetujui anggota lain. Untuk menggeser tanggal, batalkan lalu tambahkan hari libur baru."
                saatTutup={saatSelesai}
                galatUmum={galatUmum}
            >
                <div className="flex flex-col gap-4">
                    <BidangTeks
                        label="Alasan pembatalan"
                        keterangan="Misal nomor SKB perubahan."
                        nilai={formulir.data.Alasan}
                        saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                        galat={formulir.errors.Alasan}
                        maxLength={500}
                        autoFocus
                    />
                    <DialogFooter className="sm:justify-start">
                        <Tombol varian="bahaya" memproses={formulir.processing} onClick={Ajukan}>
                            Ajukan pembatalan
                        </Tombol>
                        <Tombol varian="sekunder" onClick={saatSelesai}>
                            Batal
                        </Tombol>
                    </DialogFooter>
                </div>
            </DialogFormulir>
        );
    }

    return (
        <DialogTinjauan
            judul={`Tinjau pembatalan ${hari.Nama}`}
            deskripsi={`Alasan: ${hari.AlasanPembatalan ?? '—'}. Bila disetujui, hari libur tidak lagi dipakai tenant; datanya tetap tersimpan.`}
            saatTutup={saatSelesai}
            galatUmum={galatUmum}
            aksi={
                <>
                    <Tombol varian="bahaya" memproses={formulir.processing} onClick={() => Tinjau('Setuju')}>
                        Setujui pembatalan
                    </Tombol>
                    <Tombol varian="sekunder" disabled={formulir.processing} onClick={() => Tinjau('Tolak')}>
                        Tolak pembatalan
                    </Tombol>
                </>
            }
        >
            <BidangTeks
                label="Catatan (wajib bila menolak)"
                nilai={formulir.data.Catatan}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
                maxLength={500}
            />
        </DialogTinjauan>
    );
}

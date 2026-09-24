import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import MenuAksiBaris, { type AksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Card } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Anggota = {
    Uuid: string;
    Nama: string;
    Email: string;
    KodePeran: string[];
    Aktif: boolean;
    DuaFaktorAktif: boolean;
    TerakhirMasukPada: string | null;
    DinonaktifkanPada: string | null;
};

type Undangan = { Uuid: string; Email: string; KodePeran: string[]; BerlakuSampai: string };

type Peran = { Kode: string; Nama: string };

type PropsDaftar = { Anggota: Anggota[]; Undangan: Undangan[]; Peran: Peran[] };

type Pilihan = { jenis: 'peran' | 'nonaktifkan'; anggota: Anggota } | null;

const kelasKepala = 'px-4 text-label font-semibold text-teks-sekunder';

/** Manajemen tim internal (P-01 langkah 3, 5, 6). */
export default function Daftar({ Anggota, Undangan, Peran }: PropsDaftar) {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const [formUndanganTerbuka, AturFormUndanganTerbuka] = useState(false);
    const [pilihan, AturPilihan] = useState<Pilihan>(null);
    const namaPeran = new Map(Peran.map((peran) => [peran.Kode, peran.Nama]));
    const opsiPeran = Peran.map((peran) => ({ nilai: peran.Kode, label: peran.Nama }));
    const TampilkanPeran = (kode: string[]) => kode.map((item) => namaPeran.get(item) ?? item).join(', ');

    const SusunAksi = (anggota: Anggota): AksiBaris[] => {
        const aksi: AksiBaris[] = [];
        if (PunyaIzin(pengguna, IzinPengelola.TimPeranTetapkan)) {
            aksi.push({ label: 'Ubah peran', saatPilih: () => AturPilihan({ jenis: 'peran', anggota }) });
        }
        if (PunyaIzin(pengguna, IzinPengelola.TimAnggotaNonaktifkan)) {
            aksi.push({
                label: 'Nonaktifkan',
                bahaya: true,
                saatPilih: () => AturPilihan({ jenis: 'nonaktifkan', anggota }),
            });
        }

        return aksi;
    };

    return (
        <TataLetakPengelola
            judul="Tim internal"
            aksi={
                PunyaIzin(pengguna, IzinPengelola.TimAnggotaUndang) ? (
                    <Tombol onClick={() => AturFormUndanganTerbuka(true)}>Undang anggota</Tombol>
                ) : null
            }
        >
            {formUndanganTerbuka ? (
                <FormUndangan opsiPeran={opsiPeran} saatSelesai={() => AturFormUndanganTerbuka(false)} />
            ) : null}

            {pilihan?.jenis === 'peran' ? (
                <FormPeran
                    key={pilihan.anggota.Uuid}
                    anggota={pilihan.anggota}
                    opsiPeran={opsiPeran}
                    saatSelesai={() => AturPilihan(null)}
                />
            ) : null}
            {pilihan?.jenis === 'nonaktifkan' ? (
                <FormNonaktifkan
                    key={pilihan.anggota.Uuid}
                    anggota={pilihan.anggota}
                    saatSelesai={() => AturPilihan(null)}
                />
            ) : null}

            <Card className="gap-0 py-0">
                <Table className="min-w-[720px] text-isi">
                    <TableCaption className="sr-only">Daftar anggota tim internal</TableCaption>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead scope="col" className={kelasKepala}>
                                Nama
                            </TableHead>
                            <TableHead scope="col" className={kelasKepala}>
                                Peran
                            </TableHead>
                            <TableHead scope="col" className={kelasKepala}>
                                Status
                            </TableHead>
                            <TableHead scope="col" className={kelasKepala}>
                                Terakhir masuk
                            </TableHead>
                            <TableHead scope="col" className={kelasKepala}>
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Anggota.map((anggota) => (
                            <TableRow key={anggota.Uuid} className="align-top">
                                <TableCell className="px-4 py-3 whitespace-normal">
                                    <p className="font-semibold text-teks-utama">{anggota.Nama}</p>
                                    <p className="text-keterangan text-teks-sekunder">{anggota.Email}</p>
                                </TableCell>
                                <TableCell className="px-4 py-3 whitespace-normal text-teks-utama">
                                    {TampilkanPeran(anggota.KodePeran)}
                                </TableCell>
                                <TableCell className="px-4 py-3 whitespace-normal">
                                    <div className="flex flex-wrap gap-1">
                                        {anggota.Aktif ? (
                                            <LabelStatus jenis="sukses" teks="Aktif" />
                                        ) : (
                                            <LabelStatus jenis="netral" teks="Nonaktif" />
                                        )}
                                        {anggota.DuaFaktorAktif ? null : (
                                            <LabelStatus jenis="peringatan" teks="Verifikasi dua langkah belum aktif" />
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="px-4 py-3 text-teks-sekunder">
                                    {FormatTanggalWaktu(anggota.TerakhirMasukPada)}
                                </TableCell>
                                <TableCell className="px-4 py-3 text-right">
                                    {anggota.Aktif ? (
                                        <MenuAksiBaris label={`Aksi untuk ${anggota.Nama}`} aksi={SusunAksi(anggota)} />
                                    ) : (
                                        <span className="text-keterangan text-teks-sekunder">
                                            Dinonaktifkan {FormatTanggalWaktu(anggota.DinonaktifkanPada)}
                                        </span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>

            <section className="flex flex-col gap-2">
                <h2 className="text-subjudul font-semibold text-teks-utama">Undangan menunggu</h2>
                {Undangan.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada undangan yang menunggu diterima.</p>
                ) : (
                    <Card className="gap-0 py-0">
                        <ul className="divide-y divide-garis">
                            {Undangan.map((undangan) => (
                                <li
                                    key={undangan.Uuid}
                                    className="flex flex-wrap justify-between gap-2 px-4 py-3 text-isi"
                                >
                                    <span className="font-semibold text-teks-utama">{undangan.Email}</span>
                                    <span className="text-teks-sekunder">
                                        {TampilkanPeran(undangan.KodePeran)} · berlaku sampai{' '}
                                        {FormatTanggalWaktu(undangan.BerlakuSampai)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </section>
        </TataLetakPengelola>
    );
}

type Opsi = { nilai: string; label: string }[];

function FormUndangan({ opsiPeran, saatSelesai }: { opsiPeran: Opsi; saatSelesai: () => void }) {
    const formulir = useForm<{ Email: string; KodePeran: string[] }>({ Email: '', KodePeran: [] });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/tim-internal/undangan', { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir
            judul="Undang anggota tim"
            keterangan={
                <p>
                    Undangan dikirim lewat email, berlaku 48 jam, dan hanya bisa dipakai sekali. Anggota wajib
                    mengaktifkan verifikasi dua langkah.
                </p>
            }
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Email"
                    jenis="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={formulir.errors.Email}
                    autoFocus
                    required
                />
                <GrupCentang
                    legenda="Peran"
                    opsi={opsiPeran}
                    terpilih={formulir.data.KodePeran}
                    saatBerubah={(terpilih) => formulir.setData('KodePeran', terpilih)}
                    galat={formulir.errors.KodePeran}
                />
                <div className="flex flex-wrap gap-2">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Kirim undangan
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}

function FormPeran({
    anggota,
    opsiPeran,
    saatSelesai,
}: {
    anggota: Anggota;
    opsiPeran: Opsi;
    saatSelesai: () => void;
}) {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm<{ KodePeran: string[]; Alasan: string }>({ KodePeran: anggota.KodePeran, Alasan: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(`/tim-internal/${anggota.Uuid}/peran`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir judul={`Ubah peran ${anggota.Nama}`} saatTutup={saatSelesai}>
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                <GrupCentang
                    legenda="Peran (boleh lebih dari satu)"
                    opsi={opsiPeran}
                    terpilih={formulir.data.KodePeran}
                    saatBerubah={(terpilih) => formulir.setData('KodePeran', terpilih)}
                    galat={formulir.errors.KodePeran}
                />
                <BidangTeks
                    label="Alasan (opsional)"
                    nilai={formulir.data.Alasan}
                    saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                    galat={formulir.errors.Alasan}
                    maxLength={500}
                />
                <div className="flex flex-wrap gap-2">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan peran
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}

function FormNonaktifkan({ anggota, saatSelesai }: { anggota: Anggota; saatSelesai: () => void }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm({ Alasan: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tim-internal/${anggota.Uuid}/nonaktifkan`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir
            jenis="konfirmasi"
            judul={`Nonaktifkan ${anggota.Nama}?`}
            keterangan={
                <p>
                    Sesinya langsung terputus dan ia tidak bisa masuk lagi. Akun tidak dihapus; riwayat audit tetap
                    tersimpan.
                </p>
            }
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                <BidangTeks
                    label="Alasan"
                    nilai={formulir.data.Alasan}
                    saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                    galat={formulir.errors.Alasan}
                    maxLength={500}
                    autoFocus
                    required
                />
                <div className="flex flex-wrap gap-2">
                    <Tombol type="submit" varian="bahaya" memproses={formulir.processing}>
                        Nonaktifkan akun
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}

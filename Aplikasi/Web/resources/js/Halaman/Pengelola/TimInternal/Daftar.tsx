import { useForm, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Card } from '@/Komponen/Ui/card';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Komponen/Ui/dropdown-menu';
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

function BuatKolom(namaPeran: Map<string, string>): KolomTabel<Anggota>[] {
    return [
        {
            id: 'Nama',
            accessorKey: 'Nama',
            header: 'Nama',
            meta: { label: 'Nama', prioritas: 'utama', wajib: true },
            cell: ({ row: { original: anggota } }) => (
                <>
                    <span className="block font-semibold text-teks-utama">{anggota.Nama}</span>
                    <span className="block text-keterangan font-normal break-all text-teks-sekunder">
                        {anggota.Email}
                    </span>
                </>
            ),
        },
        {
            id: 'Peran',
            header: 'Peran',
            enableSorting: false,
            meta: { label: 'Peran', prioritas: 'penting', kelasSel: 'text-teks-utama' },
            cell: ({ row }) => row.original.KodePeran.map((kode) => namaPeran.get(kode) ?? kode).join(', '),
        },
        {
            id: 'Aktif',
            accessorKey: 'Aktif',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row: { original: anggota } }) => (
                <div className="flex flex-wrap gap-1">
                    {anggota.Aktif ? (
                        <LabelStatus jenis="sukses" teks="Aktif" />
                    ) : (
                        <LabelStatus jenis="netral" teks="Nonaktif" />
                    )}
                    {anggota.DuaFaktorAktif ? null : (
                        <LabelStatus jenis="peringatan" teks="Verifikasi dua langkah belum aktif" />
                    )}
                    {anggota.Aktif ? null : (
                        <span className="block w-full text-keterangan text-teks-sekunder">
                            Dinonaktifkan {FormatTanggalWaktu(anggota.DinonaktifkanPada)}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'TerakhirMasukPada',
            accessorKey: 'TerakhirMasukPada',
            header: 'Terakhir masuk',
            meta: { label: 'Terakhir masuk', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
            cell: ({ row }) => FormatTanggalWaktu(row.original.TerakhirMasukPada),
        },
    ];
}

/** Manajemen tim internal (P-01 langkah 3, 5, 6), TabelData D-16. */
export default function Daftar({ Anggota, Undangan, Peran }: PropsDaftar) {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const [formUndanganTerbuka, AturFormUndanganTerbuka] = useState(false);
    const [pilihan, AturPilihan] = useState<Pilihan>(null);
    const namaPeran = new Map(Peran.map((peran) => [peran.Kode, peran.Nama]));
    const opsiPeran = Peran.map((peran) => ({ nilai: peran.Kode, label: peran.Nama }));
    const TampilkanPeran = (kode: string[]) => kode.map((item) => namaPeran.get(item) ?? item).join(', ');

    const bolehUbahPeran = PunyaIzin(pengguna, IzinPengelola.TimPeranTetapkan);
    const bolehNonaktifkan = PunyaIzin(pengguna, IzinPengelola.TimAnggotaNonaktifkan);
    const kolom = useMemo(() => BuatKolom(new Map(Peran.map((peran) => [peran.Kode, peran.Nama]))), [Peran]);

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

            <TabelData
                id="pengelola-tim-internal"
                label="Daftar anggota tim internal"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Anggota }}
                ambilIdBaris={(anggota) => anggota.Uuid}
                urutBawaan="Nama"
                cari="Cari nama atau email"
                saring={[
                    {
                        id: 'Aktif',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'true', label: 'Aktif' },
                            { nilai: 'false', label: 'Nonaktif' },
                        ],
                    },
                ]}
                {...(bolehUbahPeran || bolehNonaktifkan
                    ? {
                          aksiBaris: (anggota: Anggota) =>
                              anggota.Aktif ? (
                                  <>
                                      {bolehUbahPeran ? (
                                          <DropdownMenuItem onSelect={() => AturPilihan({ jenis: 'peran', anggota })}>
                                              Ubah peran
                                          </DropdownMenuItem>
                                      ) : null}
                                      {bolehUbahPeran && bolehNonaktifkan ? <DropdownMenuSeparator /> : null}
                                      {bolehNonaktifkan ? (
                                          <DropdownMenuItem
                                              variant="destructive"
                                              onSelect={() => AturPilihan({ jenis: 'nonaktifkan', anggota })}
                                          >
                                              Nonaktifkan
                                          </DropdownMenuItem>
                                      ) : null}
                                  </>
                              ) : (
                                  <DropdownMenuItem disabled>Anggota sudah dinonaktifkan</DropdownMenuItem>
                              ),
                      }
                    : {})}
                kosong={{ judul: 'Belum ada anggota tim internal. Undang anggota pertama.' }}
            />

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

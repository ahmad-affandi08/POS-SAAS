import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
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

/** Manajemen tim internal (P-01 langkah 3, 5, 6). */
export default function Daftar({ Anggota, Undangan, Peran }: PropsDaftar) {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const [formUndanganTerbuka, AturFormUndanganTerbuka] = useState(false);
    const [pilihan, AturPilihan] = useState<Pilihan>(null);
    const namaPeran = new Map(Peran.map((peran) => [peran.Kode, peran.Nama]));
    const opsiPeran = Peran.map((peran) => ({ nilai: peran.Kode, label: peran.Nama }));
    const TampilkanPeran = (kode: string[]) => kode.map((item) => namaPeran.get(item) ?? item).join(', ');

    return (
        <TataLetakPengelola
            judul="Tim internal"
            aksi={
                PunyaIzin(pengguna, IzinPengelola.TimAnggotaUndang) && !formUndanganTerbuka ? (
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

            <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                <table className="w-full min-w-[720px] text-left text-isi">
                    <caption className="sr-only">Daftar anggota tim internal</caption>
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Nama
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Peran
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Status
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Terakhir masuk
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                <span className="sr-only">Aksi</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {Anggota.map((anggota) => (
                            <tr key={anggota.Uuid} className="border-b border-garis last:border-b-0">
                                <td className="px-4 py-3 align-top">
                                    <p className="font-semibold text-teks-utama">{anggota.Nama}</p>
                                    <p className="text-keterangan text-teks-sekunder">{anggota.Email}</p>
                                </td>
                                <td className="px-4 py-3 align-top text-teks-utama">
                                    {TampilkanPeran(anggota.KodePeran)}
                                </td>
                                <td className="px-4 py-3 align-top">
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
                                </td>
                                <td className="px-4 py-3 align-top text-teks-sekunder">
                                    {FormatTanggalWaktu(anggota.TerakhirMasukPada)}
                                </td>
                                <td className="px-4 py-3 text-right align-top">
                                    {anggota.Aktif ? (
                                        <div className="flex justify-end gap-2">
                                            {PunyaIzin(pengguna, IzinPengelola.TimPeranTetapkan) ? (
                                                <Tombol
                                                    varian="sekunder"
                                                    onClick={() => AturPilihan({ jenis: 'peran', anggota })}
                                                >
                                                    Ubah peran
                                                </Tombol>
                                            ) : null}
                                            {PunyaIzin(pengguna, IzinPengelola.TimAnggotaNonaktifkan) ? (
                                                <Tombol
                                                    varian="bahaya"
                                                    onClick={() => AturPilihan({ jenis: 'nonaktifkan', anggota })}
                                                >
                                                    Nonaktifkan
                                                </Tombol>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <span className="text-keterangan text-teks-sekunder">
                                            Dinonaktifkan {FormatTanggalWaktu(anggota.DinonaktifkanPada)}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="flex flex-col gap-2">
                <h2 className="text-subjudul font-semibold text-teks-utama">Undangan menunggu</h2>
                {Undangan.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tidak ada undangan yang menunggu diterima.</p>
                ) : (
                    <ul className="divide-y divide-garis rounded-panel border border-garis bg-permukaan">
                        {Undangan.map((undangan) => (
                            <li key={undangan.Uuid} className="flex flex-wrap justify-between gap-2 px-4 py-3 text-isi">
                                <span className="font-semibold text-teks-utama">{undangan.Email}</span>
                                <span className="text-teks-sekunder">
                                    {TampilkanPeran(undangan.KodePeran)} · berlaku sampai{' '}
                                    {FormatTanggalWaktu(undangan.BerlakuSampai)}
                                </span>
                            </li>
                        ))}
                    </ul>
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
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">Undang anggota tim</h2>
            <p className="text-isi text-teks-sekunder">
                Undangan dikirim lewat email, berlaku 48 jam, dan hanya bisa dipakai sekali. Anggota wajib mengaktifkan
                verifikasi dua langkah.
            </p>
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
            <div className="flex gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Kirim undangan
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
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
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">Ubah peran {anggota.Nama}</h2>
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
            <div className="flex gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan peran
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
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
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-bahaya bg-permukaan p-6"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">Nonaktifkan {anggota.Nama}?</h2>
            <p className="text-isi text-teks-sekunder">
                Sesinya langsung terputus dan ia tidak bisa masuk lagi. Akun tidak dihapus; riwayat audit tetap
                tersimpan.
            </p>
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
            <div className="flex gap-2">
                <Tombol type="submit" varian="bahaya" memproses={formulir.processing}>
                    Nonaktifkan akun
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

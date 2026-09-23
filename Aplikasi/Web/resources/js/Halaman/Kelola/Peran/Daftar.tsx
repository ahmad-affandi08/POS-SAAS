import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabPengguna from '@/Komponen/Kelola/TabPengguna';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant } from '@/Tipe/Organisasi';

type Peran = {
    Uuid: string;
    Nama: string;
    Keterangan: string | null;
    Bawaan: boolean;
    Pemilik: boolean;
    Izin: string[];
    JumlahAnggota: number;
};

type Izin = { Kunci: string; Label: string; Kelompok: string; KhususPemilik: boolean };

type PropsDaftar = { Peran: Peran[]; DaftarIzin: Izin[] };

/** Peran & izin tenant (PRD §19.1): peran bawaan hanya dibaca; peran kustom bisa dibuat dari izin granular. */
export default function HalamanDaftarPeran({ Peran, DaftarIzin }: PropsDaftar) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.PeranKelola);
    const [sunting, AturSunting] = useState<Peran | 'baru' | null>(null);
    const labelIzin = new Map(DaftarIzin.map((izin) => [izin.Kunci, izin.Label]));

    return (
        <TataLetakAplikasi judul="Pengguna & peran">
            <TabPengguna />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Peran bawaan disiapkan sistem. Buat peran kustom bila tim Anda butuh kombinasi izin lain.
                </p>
                {bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Buat peran</Tombol>
                ) : null}
            </div>

            {sunting !== null ? (
                <FormPeran
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    peran={sunting === 'baru' ? null : sunting}
                    daftarIzin={DaftarIzin}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            <ul className="flex flex-col divide-y divide-garis rounded-panel border border-garis bg-permukaan">
                {Peran.map((peran) => (
                    <li key={peran.Uuid} className="flex flex-col gap-2 px-4 py-3">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p className="flex flex-wrap items-center gap-2 font-semibold text-teks-utama">
                                    {peran.Nama}
                                    <LabelStatus jenis="netral" teks={peran.Bawaan ? 'Bawaan' : 'Kustom'} />
                                </p>
                                {peran.Keterangan ? (
                                    <p className="text-keterangan text-teks-sekunder">{peran.Keterangan}</p>
                                ) : null}
                                <p className="text-keterangan text-teks-sekunder">
                                    {peran.JumlahAnggota} anggota aktif
                                </p>
                            </div>
                            {bolehKelola && !peran.Bawaan ? (
                                <span className="flex gap-2">
                                    <Tombol varian="sekunder" onClick={() => AturSunting(peran)}>
                                        Ubah peran
                                    </Tombol>
                                    {peran.JumlahAnggota === 0 ? (
                                        <Tombol
                                            varian="bahaya"
                                            onClick={() =>
                                                router.delete(`/kelola/peran/${peran.Uuid}`, { preserveScroll: true })
                                            }
                                        >
                                            Hapus peran
                                        </Tombol>
                                    ) : null}
                                </span>
                            ) : null}
                        </div>
                        <p className="text-keterangan text-teks-sekunder">
                            {peran.Pemilik
                                ? 'Semua izin, termasuk langganan.'
                                : peran.Izin.map((kunci) => labelIzin.get(kunci) ?? kunci).join(' · ') ||
                                  'Belum ada izin.'}
                        </p>
                    </li>
                ))}
            </ul>
        </TataLetakAplikasi>
    );
}

function FormPeran({
    peran,
    daftarIzin,
    saatSelesai,
}: {
    peran: Peran | null;
    daftarIzin: Izin[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({ Nama: peran?.Nama ?? '', Keterangan: peran?.Keterangan ?? '', Izin: peran?.Izin ?? [] });
    // Izin khusus Pemilik (langganan) tidak bisa masuk peran kustom.
    const kelompok = [...new Set(daftarIzin.filter((izin) => !izin.KhususPemilik).map((izin) => izin.Kelompok))];

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (peran === null) {
            formulir.post('/kelola/peran', opsi);
        } else {
            formulir.put(`/kelola/peran/${peran.Uuid}`, opsi);
        }
    };

    const UbahKelompok = (namaKelompok: string, terpilih: string[]) => {
        const lain = formulir.data.Izin.filter(
            (kunci) => daftarIzin.find((izin) => izin.Kunci === kunci)?.Kelompok !== namaKelompok,
        );
        formulir.setData('Izin', [...lain, ...terpilih]);
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">
                {peran === null ? 'Buat peran' : `Ubah ${peran.Nama}`}
            </h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <BidangTeks
                    label="Nama peran"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                    maxLength={100}
                    autoFocus
                    required
                />
                <BidangTeks
                    label="Keterangan (opsional)"
                    nilai={formulir.data.Keterangan}
                    saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                    galat={formulir.errors.Keterangan}
                    maxLength={255}
                />
            </div>
            {kelompok.map((namaKelompok) => (
                <GrupCentang
                    key={namaKelompok}
                    legenda={namaKelompok}
                    opsi={daftarIzin
                        .filter((izin) => izin.Kelompok === namaKelompok && !izin.KhususPemilik)
                        .map((izin) => ({ nilai: izin.Kunci, label: izin.Label }))}
                    terpilih={formulir.data.Izin.filter(
                        (kunci) => daftarIzin.find((izin) => izin.Kunci === kunci)?.Kelompok === namaKelompok,
                    )}
                    saatBerubah={(terpilih) => UbahKelompok(namaKelompok, terpilih)}
                />
            ))}
            {formulir.errors.Izin ? (
                <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.Izin}</p>
            ) : null}
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

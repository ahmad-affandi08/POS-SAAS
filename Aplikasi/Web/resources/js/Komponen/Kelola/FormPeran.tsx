import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import type { IzinPeran } from '@/Tipe/Organisasi';

export type IsianPeran = { Nama: string; Keterangan: string; Izin: string[] };

type PropsFormPeran = {
    /** Null = buat peran baru; selain itu Uuid peran kustom yang diubah. */
    uuid: string | null;
    awal: IsianPeran;
    daftarIzin: IzinPeran[];
    /** Dipanggil setelah tersimpan (panel ubah menutup diri). Halaman buat tidak memakainya: server mengarahkan. */
    saatSelesai?: () => void;
    saatBatal: () => void;
};

/** Isian peran kustom (PRD §19.1): nama, keterangan, dan izin granular per kelompok. */
export default function FormPeran({ uuid, awal, daftarIzin, saatSelesai, saatBatal }: PropsFormPeran) {
    const formulir = useForm<IsianPeran>(awal);
    // Izin khusus Pemilik (langganan) tidak bisa masuk peran kustom.
    const kelompok = [...new Set(daftarIzin.filter((izin) => !izin.KhususPemilik).map((izin) => izin.Kelompok))];

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: () => saatSelesai?.() };

        if (uuid === null) {
            formulir.post('/kelola/peran', opsi);
        } else {
            formulir.put(`/kelola/peran/${uuid}`, opsi);
        }
    };

    const UbahKelompok = (namaKelompok: string, terpilih: string[]) => {
        const lain = formulir.data.Izin.filter(
            (kunci) => daftarIzin.find((izin) => izin.Kunci === kunci)?.Kelompok !== namaKelompok,
        );
        formulir.setData('Izin', [...lain, ...terpilih]);
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
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
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan peran
                </Tombol>
                <Tombol varian="sekunder" onClick={saatBatal}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

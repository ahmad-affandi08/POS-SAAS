import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import {
    IzinPengelola,
    PunyaIzin,
    type DaftarBerhalaman,
    type Pilihan,
    type PropsBersamaPengelola,
} from '@/Tipe/Pengelola';

type Wilayah = { Kode: string; Nama: string; Tingkat: string; KodeInduk: string | null; ZonaWaktu: string };

type PropsWilayah = {
    Wilayah: DaftarBerhalaman<Wilayah>;
    Saring: { Kata: string; Tingkat: string | null };
    PilihanTingkat: Pilihan[];
    PilihanZonaWaktu: string[];
};

/** Data wilayah resmi (P-02). Muat massal lewat perintah server `pengelola:impor-wilayah`. */
export default function HalamanWilayah({ Wilayah, Saring, PilihanTingkat, PilihanZonaWaktu }: PropsWilayah) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiWilayahKelola);
    const [kata, AturKata] = useState(Saring.Kata);
    const [tingkat, AturTingkat] = useState(Saring.Tingkat ?? '');
    const [sunting, AturSunting] = useState<Wilayah | 'baru' | null>(null);
    const labelTingkat = new Map(PilihanTingkat.map((item) => [item.Nilai, item.Label]));
    const saringAktif = {
        ...(Saring.Kata ? { kata: Saring.Kata } : {}),
        ...(Saring.Tingkat ? { tingkat: Saring.Tingkat } : {}),
    };

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get(
            '/referensi/wilayah',
            { ...(kata ? { kata } : {}), ...(tingkat ? { tingkat } : {}) },
            { preserveState: true },
        );
    };

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah wilayah</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormWilayah
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    wilayah={sunting === 'baru' ? null : sunting}
                    pilihanTingkat={PilihanTingkat}
                    pilihanZonaWaktu={PilihanZonaWaktu}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            <form onSubmit={Cari} className="flex flex-wrap items-end gap-2">
                <div className="w-full max-w-xs">
                    <BidangTeks label="Cari nama atau kode" nilai={kata} saatBerubah={AturKata} />
                </div>
                <div className="w-48">
                    <BidangPilihan
                        label="Tingkat"
                        nilai={tingkat}
                        opsi={PilihanTingkat}
                        saatBerubah={AturTingkat}
                        kosong="Semua"
                    />
                </div>
                <Tombol type="submit" varian="sekunder">
                    Cari
                </Tombol>
            </form>

            {Wilayah.Data.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada wilayah">
                    {Saring.Kata || Saring.Tingkat
                        ? 'Tidak ada wilayah yang cocok dengan pencarian.'
                        : 'Muat data resmi dengan perintah server: php artisan pengelola:impor-wilayah wilayah.csv'}
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar wilayah</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Tingkat
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Zona waktu
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Wilayah.Data.map((wilayah) => (
                                <tr key={wilayah.Kode} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label">{wilayah.Kode}</td>
                                    <td className="px-4 py-2 text-teks-utama">{wilayah.Nama}</td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {labelTingkat.get(wilayah.Tingkat) ?? wilayah.Tingkat}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">{wilayah.ZonaWaktu}</td>
                                    <td className="px-4 py-2 text-right">
                                        {bolehKelola ? (
                                            <Tombol varian="sekunder" onClick={() => AturSunting(wilayah)}>
                                                Ubah
                                            </Tombol>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
            <Paginasi
                alamat="/referensi/wilayah"
                saring={saringAktif}
                halamanSaatIni={Wilayah.HalamanSaatIni}
                halamanTerakhir={Wilayah.HalamanTerakhir}
                total={Wilayah.Total}
                label="Halaman wilayah"
            />
        </TataLetakPengelola>
    );
}

type PropsFormWilayah = {
    wilayah: Wilayah | null;
    pilihanTingkat: Pilihan[];
    pilihanZonaWaktu: string[];
    saatSelesai: () => void;
};

function FormWilayah({ wilayah, pilihanTingkat, pilihanZonaWaktu, saatSelesai }: PropsFormWilayah) {
    const formulir = useForm({
        Kode: wilayah?.Kode ?? '',
        Nama: wilayah?.Nama ?? '',
        Tingkat: wilayah?.Tingkat ?? 'KabupatenKota',
        KodeInduk: wilayah?.KodeInduk ?? '',
        ZonaWaktu: wilayah?.ZonaWaktu ?? 'WIB',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (wilayah === null) {
            formulir.post('/referensi/wilayah', opsi);
        } else {
            formulir.put(`/referensi/wilayah/${encodeURIComponent(wilayah.Kode)}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {wilayah === null ? 'Tambah wilayah' : `Ubah ${wilayah.Nama}`}
            </h2>
            <BidangTeks
                label="Kode resmi"
                kode
                keterangan="Provinsi 2 digit (33), kabupaten/kota 33.74. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                galat={formulir.errors.Kode}
                disabled={wilayah !== null}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangPilihan
                label="Tingkat"
                nilai={formulir.data.Tingkat}
                opsi={pilihanTingkat}
                saatBerubah={(nilai) => formulir.setData('Tingkat', nilai)}
                galat={formulir.errors.Tingkat}
            />
            <BidangTeks
                label="Kode provinsi induk"
                kode
                keterangan="Kosongkan untuk provinsi."
                nilai={formulir.data.KodeInduk}
                saatBerubah={(nilai) => formulir.setData('KodeInduk', nilai)}
                galat={formulir.errors.KodeInduk}
            />
            <BidangPilihan
                label="Zona waktu"
                nilai={formulir.data.ZonaWaktu}
                opsi={pilihanZonaWaktu.map((zona) => ({ Nilai: zona, Label: zona }))}
                saatBerubah={(nilai) => formulir.setData('ZonaWaktu', nilai)}
                galat={formulir.errors.ZonaWaktu}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan wilayah
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

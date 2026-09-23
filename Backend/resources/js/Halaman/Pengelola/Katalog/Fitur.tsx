import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Fitur = { Kunci: string; Nama: string; Modul: string; Keterangan: string | null };

/** Katalog fitur (P-04). Kunci fitur dipakai kode aplikasi dan tidak bisa diubah. */
export default function HalamanFitur({ Fitur }: { Fitur: Fitur[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.KatalogFiturKelola);
    const [sunting, AturSunting] = useState<Fitur | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Katalog"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah fitur</Tombol>
                ) : null
            }
        >
            <TabKatalog />
            {sunting !== null ? (
                <FormFitur
                    key={sunting === 'baru' ? 'baru' : sunting.Kunci}
                    fitur={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {Fitur.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada fitur">
                    Jalankan seeder database untuk memuat katalog fitur awal.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Katalog fitur</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kunci
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Modul
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Fitur.map((fitur) => (
                                <tr key={fitur.Kunci} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label">{fitur.Kunci}</td>
                                    <td className="px-4 py-2 text-teks-utama">
                                        {fitur.Nama}
                                        {fitur.Keterangan ? (
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {fitur.Keterangan}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">{fitur.Modul}</td>
                                    <td className="px-4 py-2 text-right">
                                        {bolehKelola ? (
                                            <Tombol varian="sekunder" onClick={() => AturSunting(fitur)}>
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
        </TataLetakPengelola>
    );
}

function FormFitur({ fitur, saatSelesai }: { fitur: Fitur | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Kunci: fitur?.Kunci ?? '',
        Nama: fitur?.Nama ?? '',
        Modul: fitur?.Modul ?? '',
        Keterangan: fitur?.Keterangan ?? '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (fitur === null) {
            formulir.post('/katalog/fitur', opsi);
        } else {
            formulir.put(`/katalog/fitur/${encodeURIComponent(fitur.Kunci)}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {fitur === null ? 'Tambah fitur' : `Ubah ${fitur.Nama}`}
            </h2>
            <BidangTeks
                label="Kunci"
                kode
                keterangan="Huruf kecil dipisah titik, misal pos.mode-meja. Tidak bisa diubah."
                nilai={formulir.data.Kunci}
                saatBerubah={(nilai) => formulir.setData('Kunci', nilai.toLowerCase())}
                galat={formulir.errors.Kunci}
                disabled={fitur !== null}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangTeks
                label="Modul"
                nilai={formulir.data.Modul}
                saatBerubah={(nilai) => formulir.setData('Modul', nilai)}
                galat={formulir.errors.Modul}
            />
            <BidangTeks
                label="Keterangan (opsional)"
                nilai={formulir.data.Keterangan}
                saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                galat={formulir.errors.Keterangan}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan fitur
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

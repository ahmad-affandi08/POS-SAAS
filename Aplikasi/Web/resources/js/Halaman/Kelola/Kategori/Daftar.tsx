import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDaftarKategori } from '@/Tipe/Katalog';

type Kategori = PropsDaftarKategori['Kategori'][number];

/** Kedalaman maksimal kategori (config katalog.Kategori.MaksimalKedalaman). */
export const MaksimalKedalamanKategori = 3;

/** Uuid kategori beserta seluruh turunannya (tidak boleh dipilih sebagai induk barunya sendiri). */
export function AmbilTurunanKategori(kategori: Kategori[], uuid: string): Set<string> {
    const hasil = new Set([uuid]);
    let bertambah = true;

    while (bertambah) {
        bertambah = false;

        for (const item of kategori) {
            if (item.UuidInduk !== null && hasil.has(item.UuidInduk) && !hasil.has(item.Uuid)) {
                hasil.add(item.Uuid);
                bertambah = true;
            }
        }
    }

    return hasil;
}

/** Indentasi baris menurut tingkat kategori (1–3). */
function KelasIndentasi(kedalaman: number): string {
    return kedalaman >= 3 ? 'pl-10' : kedalaman === 2 ? 'pl-5' : '';
}

function FormKategori({
    kategori,
    semua,
    saatSelesai,
}: {
    kategori: Kategori | null;
    semua: Kategori[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        Nama: kategori?.Nama ?? '',
        UuidInduk: kategori?.UuidInduk ?? null,
        Urutan: kategori ? String(kategori.Urutan) : '0',
    });
    const galat = formulir.errors as Record<string, string | undefined>;
    const terlarang = kategori ? AmbilTurunanKategori(semua, kategori.Uuid) : new Set<string>();
    const opsiInduk = semua.filter((item) => item.Kedalaman < MaksimalKedalamanKategori && !terlarang.has(item.Uuid));

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (kategori === null) {
            formulir.post('/kelola/kategori', opsi);
        } else {
            formulir.put(`/kelola/kategori/${kategori.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-label={kategori ? `Ubah kategori ${kategori.Nama}` : 'Tambah kategori'}
            className="grid gap-3 rounded-panel border border-garis bg-permukaan p-4 sm:grid-cols-3"
        >
            <BidangTeks
                label="Nama kategori"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={galat.Nama}
                maxLength={100}
                autoFocus
                required
            />
            <BidangPilihan
                label="Induk kategori"
                nilai={formulir.data.UuidInduk ?? ''}
                kosong="Tanpa induk (tingkat teratas)"
                opsi={opsiInduk.map((item) => ({ Nilai: item.Uuid, Label: item.Jalur }))}
                saatBerubah={(nilai) => formulir.setData('UuidInduk', nilai === '' ? null : nilai)}
                galat={galat.UuidInduk}
            />
            <BidangTeks
                label="Urutan tampil"
                nilai={formulir.data.Urutan}
                saatBerubah={(nilai) => formulir.setData('Urutan', nilai.replace(/\D/g, ''))}
                galat={galat.Urutan}
                inputMode="numeric"
                maxLength={4}
                keterangan="Angka kecil tampil lebih dulu di kasir."
            />
            <div className="flex flex-wrap gap-2 sm:col-span-3">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kategori
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

/** F-03 kategori bertingkat (maks 3 tingkat). Hapus hanya bila tanpa sub-kategori dan tanpa produk. */
export default function HalamanDaftarKategori({ Kategori, Izin }: PropsDaftarKategori) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<Kategori | 'baru' | null>(null);
    const CekPunyaAnak = (uuid: string) => Kategori.some((item) => item.UuidInduk === uuid);

    return (
        <TataLetakAplikasi judul="Kategori produk">
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="kategori" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={sunting !== null ? ['Nama', 'UuidInduk', 'Urutan'] : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-isi text-teks-sekunder">
                    Kelompokkan produk sampai 3 tingkat, misal Minuman › Kopi › Kopi susu.
                </p>
                {Izin.Kelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah kategori</Tombol>
                ) : null}
            </div>
            {sunting !== null ? (
                <FormKategori
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    kategori={sunting === 'baru' ? null : sunting}
                    semua={Kategori}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {Kategori.length === 0 ? (
                <KeadaanKosong judul="Belum ada kategori. Tambah kategori agar produk mudah dicari di kasir." />
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[560px] text-left text-isi">
                        <caption className="sr-only">Daftar kategori</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kategori
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Urutan
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Produk
                                </th>
                                {Izin.Kelola ? (
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody>
                            {Kategori.map((item) => (
                                <tr key={item.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2">
                                        <span
                                            className={`block font-semibold break-words text-teks-utama ${KelasIndentasi(item.Kedalaman)}`}
                                        >
                                            {item.Nama}
                                        </span>
                                        {item.Kedalaman > 1 ? (
                                            <span
                                                className={`block text-keterangan text-teks-sekunder ${KelasIndentasi(item.Kedalaman)}`}
                                            >
                                                {item.Jalur}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums text-teks-sekunder">
                                        {item.Urutan}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">{item.JumlahProduk}</td>
                                    {Izin.Kelola ? (
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            <span className="flex gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => AturSunting(item)}
                                                    className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                    aria-label={`Ubah kategori ${item.Nama}`}
                                                >
                                                    Ubah
                                                </button>
                                                {item.JumlahProduk === 0 && !CekPunyaAnak(item.Uuid) ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.delete(`/kelola/kategori/${item.Uuid}`, {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                        className="text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                        aria-label={`Hapus kategori ${item.Nama}`}
                                                    >
                                                        Hapus
                                                    </button>
                                                ) : null}
                                            </span>
                                        </td>
                                    ) : null}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakAplikasi>
    );
}

import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type AnggotaPin = { Uuid: string; Nama: string; Email: string; NamaPeran: string | null; PinDiatur: boolean };

type PropsPin = { PinSayaDiatur: boolean; Anggota: AnggotaPin[] | null };

/** PIN kasir 6 angka: atur PIN sendiri, atur ulang PIN anggota (F-02 langkah 4, §20.2). */
export default function HalamanPin({ PinSayaDiatur, Anggota }: PropsPin) {
    const [sunting, AturSunting] = useState<AnggotaPin | null>(null);

    return (
        <TataLetakAplikasi judul="PIN kasir">
            <p className="text-isi text-teks-sekunder">
                <Link href="/kelola/keamanan" className="font-semibold text-brand underline">
                    Keamanan akun
                </Link>{' '}
                / PIN kasir
            </p>
            <section className="flex max-w-xl flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6">
                <header className="flex flex-col gap-1">
                    <h2 className="text-subjudul font-bold text-teks-utama">PIN saya</h2>
                    <p className="text-isi text-teks-sekunder">
                        Dipakai untuk masuk cepat di perangkat kasir bersama. Jangan pakai angka yang sama semua atau
                        berurutan. Setelah 5 kali salah, PIN terkunci 5 menit di perangkat itu.
                    </p>
                    <p className="text-label font-semibold text-teks-utama">
                        Status: {PinSayaDiatur ? 'Sudah diatur' : 'Belum diatur'}
                    </p>
                </header>
                <FormPin alamat="/kelola/keamanan/pin" labelTombol={PinSayaDiatur ? 'Ganti PIN' : 'Simpan PIN'} />
            </section>

            {Anggota ? (
                <section className="flex flex-col gap-2">
                    <h2 className="text-subjudul font-semibold text-teks-utama">PIN anggota</h2>
                    <p className="text-keterangan text-teks-sekunder">
                        PIN tidak bisa dilihat. Bila anggota lupa PIN, atur PIN baru lalu beritahukan langsung
                        kepadanya.
                    </p>
                    {sunting ? (
                        <div className="flex max-w-xl flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4">
                            <h3 className="text-label font-semibold text-teks-utama">Atur ulang PIN {sunting.Nama}</h3>
                            <FormPin
                                key={sunting.Uuid}
                                alamat={`/kelola/pengguna/${sunting.Uuid}/pin`}
                                labelTombol="Simpan PIN baru"
                                saatSelesai={() => AturSunting(null)}
                            />
                        </div>
                    ) : null}
                    {Anggota.length === 0 ? (
                        <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                            Belum ada anggota lain yang PIN-nya bisa Anda atur.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                            <table className="w-full min-w-[560px] text-left text-isi">
                                <caption className="sr-only">Status PIN anggota</caption>
                                <thead className="border-b border-garis text-label text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className="px-4 py-2 font-semibold">
                                            Nama
                                        </th>
                                        <th scope="col" className="px-4 py-2 font-semibold">
                                            Peran
                                        </th>
                                        <th scope="col" className="px-4 py-2 font-semibold">
                                            PIN
                                        </th>
                                        <th scope="col" className="px-4 py-2 font-semibold">
                                            <span className="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Anggota.map((anggota) => (
                                        <tr key={anggota.Uuid} className="border-b border-garis last:border-b-0">
                                            <td className="px-4 py-2 text-teks-utama">
                                                {anggota.Nama}
                                                <span className="block text-keterangan text-teks-sekunder">
                                                    {anggota.Email}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 text-teks-sekunder">{anggota.NamaPeran ?? '—'}</td>
                                            <td className="px-4 py-2">
                                                {anggota.PinDiatur ? (
                                                    <LabelStatus jenis="sukses" teks="Sudah diatur" />
                                                ) : (
                                                    <LabelStatus jenis="peringatan" teks="Belum diatur" />
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                <Tombol varian="sekunder" onClick={() => AturSunting(anggota)}>
                                                    Atur ulang PIN
                                                </Tombol>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            ) : null}
        </TataLetakAplikasi>
    );
}

type PropsFormPin = { alamat: string; labelTombol: string; saatSelesai?: () => void };

function FormPin({ alamat, labelTombol, saatSelesai }: PropsFormPin) {
    const formulir = useForm({ Pin: '', KonfirmasiPin: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(alamat, {
            preserveScroll: true,
            onFinish: () => formulir.reset(),
            ...(saatSelesai ? { onSuccess: saatSelesai } : {}),
        });
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
            <BidangTeks
                label="PIN baru (6 angka)"
                jenis="password"
                inputMode="numeric"
                autoComplete="new-password"
                maxLength={6}
                kode
                nilai={formulir.data.Pin}
                saatBerubah={(nilai) => formulir.setData('Pin', nilai.replace(/\D/g, ''))}
                galat={formulir.errors.Pin}
                required
            />
            <BidangTeks
                label="Ulangi PIN"
                jenis="password"
                inputMode="numeric"
                autoComplete="new-password"
                maxLength={6}
                kode
                nilai={formulir.data.KonfirmasiPin}
                saatBerubah={(nilai) => formulir.setData('KonfirmasiPin', nilai.replace(/\D/g, ''))}
                galat={formulir.errors.KonfirmasiPin}
                required
            />
            <div className="flex gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    {labelTombol}
                </Tombol>
                {saatSelesai ? (
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                ) : null}
            </div>
        </form>
    );
}

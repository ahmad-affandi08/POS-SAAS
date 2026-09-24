import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsDaftarSatuan } from '@/Tipe/Katalog';

type Satuan = PropsDaftarSatuan['Satuan'][number];

function FormSatuan({ satuan, saatSelesai }: { satuan: Satuan | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Nama: satuan?.Nama ?? '',
        Simbol: satuan?.Simbol ?? '',
        BolehDesimal: satuan?.BolehDesimal ?? false,
    });
    const galat = formulir.errors as Record<string, string | undefined>;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (satuan === null) {
            formulir.post('/kelola/satuan', opsi);
        } else {
            formulir.put(`/kelola/satuan/${satuan.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-label={satuan ? `Ubah satuan ${satuan.Nama}` : 'Tambah satuan'}
            className="grid gap-3 rounded-panel border border-garis bg-permukaan p-4 sm:grid-cols-3"
        >
            <BidangTeks
                label="Nama satuan"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={galat.Nama}
                keterangan="Misal Kilogram, Porsi, atau Dus."
                maxLength={50}
                autoFocus
                required
            />
            <BidangTeks
                label="Simbol"
                nilai={formulir.data.Simbol}
                saatBerubah={(nilai) => formulir.setData('Simbol', nilai)}
                galat={galat.Simbol}
                keterangan="Tampil di struk dan tabel, misal kg."
                maxLength={10}
                required
            />
            <div className="flex flex-col gap-1">
                <KotakCentang
                    label="Boleh pecahan (misal 0,5 kg)"
                    nilai={formulir.data.BolehDesimal}
                    saatBerubah={(nilai) => formulir.setData('BolehDesimal', nilai)}
                />
                {galat.BolehDesimal ? (
                    <p className="text-keterangan font-semibold text-bahaya">{galat.BolehDesimal}</p>
                ) : (
                    <p className="text-keterangan text-teks-sekunder">
                        Tidak bisa diubah bila satuan ini sudah menjadi satuan dasar produk.
                    </p>
                )}
            </div>
            <div className="flex flex-wrap gap-2 sm:col-span-3">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan satuan
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

/** F-03 satuan ukur tenant: standar (dari referensi) dan buatan sendiri. */
export default function HalamanDaftarSatuan({ Satuan, Izin }: PropsDaftarSatuan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<Satuan | 'baru' | null>(null);

    return (
        <TataLetakAplikasi judul="Satuan">
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="satuan" /> : null}
            <DaftarGalatServer
                galat={props.errors}
                kecuali={sunting !== null ? ['Nama', 'Simbol', 'BolehDesimal'] : []}
            />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-isi text-teks-sekunder">
                    Satuan dipakai untuk stok, harga, dan resep. Konversi (misal 1 dus = 24 pcs) diatur per produk.
                </p>
                {Izin.Kelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah satuan</Tombol>
                ) : null}
            </div>
            {sunting !== null ? (
                <FormSatuan
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    satuan={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {Satuan.length === 0 ? (
                <KeadaanKosong judul="Belum ada satuan. Tambah satuan, misal pcs atau kg." />
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[560px] text-left text-isi">
                        <caption className="sr-only">Daftar satuan</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Satuan
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pecahan
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Asal
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
                            {Satuan.map((item) => (
                                <tr key={item.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2">
                                        <span className="font-semibold text-teks-utama">{item.Nama}</span>{' '}
                                        <span className="text-teks-sekunder">({item.Simbol})</span>
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {item.BolehDesimal ? 'Boleh' : 'Tidak'}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {item.KodeStandar ? (
                                            <>
                                                Standar <span className="font-mono">{item.KodeStandar}</span>
                                            </>
                                        ) : (
                                            'Buatan sendiri'
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">{item.JumlahProduk}</td>
                                    {Izin.Kelola ? (
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            <span className="flex gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => AturSunting(item)}
                                                    className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                    aria-label={`Ubah satuan ${item.Nama}`}
                                                >
                                                    Ubah
                                                </button>
                                                {item.JumlahProduk === 0 ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.delete(`/kelola/satuan/${item.Uuid}`, {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                        className="text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                        aria-label={`Hapus satuan ${item.Nama}`}
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

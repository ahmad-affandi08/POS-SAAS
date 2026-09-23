import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import FormOutlet from '@/Komponen/Kelola/FormOutlet';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import {
    CekBatasPenuh,
    FormatBatas,
    IzinTenant,
    PunyaIzinTenant,
    type Batas,
    type Kota,
    type StatusOrganisasi,
} from '@/Tipe/Organisasi';

type Outlet = {
    Uuid: string;
    Kode: string;
    Nama: string;
    NamaMerek: string | null;
    NamaKota: string | null;
    ZonaWaktu: string;
    JamTutupBuku: string;
    JumlahGudang: number;
    Status: StatusOrganisasi;
};

type Merek = { Uuid: string; Nama: string; JumlahOutlet: number };

type PropsDaftar = { Outlet: Outlet[]; Merek: Merek[]; Kota: Kota[]; BatasOutlet: Batas };

/** Daftar outlet & merek (F-02 langkah 1, BR-02.1). */
export default function HalamanDaftarOutlet({ Outlet, Merek, Kota, BatasOutlet }: PropsDaftar) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.OutletKelola);
    const [formTerbuka, AturFormTerbuka] = useState(false);
    const penuh = CekBatasPenuh(BatasOutlet);

    return (
        <TataLetakAplikasi judul="Outlet">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Outlet aktif:{' '}
                    <span className="font-semibold text-teks-utama">{FormatBatas(BatasOutlet, 'outlet')}</span>
                </p>
                {bolehKelola && !formTerbuka ? (
                    <Tombol onClick={() => AturFormTerbuka(true)} disabled={penuh}>
                        Tambah outlet
                    </Tombol>
                ) : null}
            </div>

            {bolehKelola && penuh ? (
                <Pemberitahuan jenis="info" judul="Batas outlet paket sudah tercapai">
                    Tingkatkan paket atau tambah add-on outlet di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    . Outlet yang diarsipkan tidak dihitung.
                </Pemberitahuan>
            ) : null}

            {formTerbuka ? (
                <FormOutlet
                    uuid={null}
                    awal={{
                        Nama: '',
                        Kode: '',
                        Merek: Merek[0]?.Uuid ?? '',
                        Alamat: '',
                        KodeKota: '',
                        ZonaWaktu: 'WIB',
                        JamTutupBuku: '04:00',
                        Pkp: false,
                        Nitku: '',
                        PungutPbjt: false,
                    }}
                    merek={Merek.map((baris) => ({ Nilai: baris.Uuid, Label: baris.Nama }))}
                    kota={Kota}
                    saatBatal={() => AturFormTerbuka(false)}
                />
            ) : null}

            {Outlet.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    Belum ada outlet yang bisa Anda akses. Minta Owner menugaskan Anda ke outlet.
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[820px] text-left text-isi">
                        <caption className="sr-only">Daftar outlet</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Outlet
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kota
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Tutup buku
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Lokasi stok
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Outlet.map((outlet) => (
                                <tr key={outlet.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label text-teks-utama">{outlet.Kode}</td>
                                    <td className="px-4 py-2">
                                        <Link
                                            href={`/kelola/outlet/${outlet.Uuid}`}
                                            className="font-semibold text-brand underline"
                                        >
                                            {outlet.Nama}
                                        </Link>
                                        {outlet.NamaMerek ? (
                                            <span className="block text-keterangan text-teks-sekunder">
                                                {outlet.NamaMerek}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {outlet.NamaKota ?? 'Belum diisi'} · {outlet.ZonaWaktu}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-label text-teks-sekunder">
                                        {outlet.JamTutupBuku}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums text-teks-utama">
                                        {outlet.JumlahGudang}
                                    </td>
                                    <td className="px-4 py-2">
                                        {outlet.Status === 'Aktif' ? (
                                            <LabelStatus jenis="sukses" teks="Aktif" />
                                        ) : (
                                            <LabelStatus jenis="netral" teks="Diarsipkan" />
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            <BagianMerek merek={Merek} bolehKelola={bolehKelola} />
        </TataLetakAplikasi>
    );
}

function BagianMerek({ merek, bolehKelola }: { merek: Merek[]; bolehKelola: boolean }) {
    const [sunting, AturSunting] = useState<Merek | 'baru' | null>(null);

    return (
        <section className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-subjudul font-semibold text-teks-utama">Merek</h2>
                {bolehKelola && sunting === null ? (
                    <Tombol varian="sekunder" onClick={() => AturSunting('baru')}>
                        Tambah merek
                    </Tombol>
                ) : null}
            </div>
            <p className="text-keterangan text-teks-sekunder">
                Pakai lebih dari satu merek bila usaha Anda punya beberapa nama dagang, misal kafe dan toko roti.
            </p>
            {sunting !== null ? (
                <FormMerek
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    merek={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            <ul className="divide-y divide-garis rounded-panel border border-garis bg-permukaan">
                {merek.map((baris) => (
                    <li
                        key={baris.Uuid}
                        className="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-isi"
                    >
                        <span>
                            <span className="font-semibold text-teks-utama">{baris.Nama}</span>
                            <span className="text-teks-sekunder"> · {baris.JumlahOutlet} outlet</span>
                        </span>
                        {bolehKelola ? (
                            <span className="flex gap-2">
                                <Tombol varian="sekunder" onClick={() => AturSunting(baris)}>
                                    Ganti nama
                                </Tombol>
                                {baris.JumlahOutlet === 0 && merek.length > 1 ? (
                                    <Tombol
                                        varian="bahaya"
                                        onClick={() =>
                                            router.delete(`/kelola/merek/${baris.Uuid}`, { preserveScroll: true })
                                        }
                                    >
                                        Hapus merek
                                    </Tombol>
                                ) : null}
                            </span>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}

function FormMerek({ merek, saatSelesai }: { merek: Merek | null; saatSelesai: () => void }) {
    const formulir = useForm({ Nama: merek?.Nama ?? '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (merek === null) {
            formulir.post('/kelola/merek', opsi);
        } else {
            formulir.put(`/kelola/merek/${merek.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-wrap items-end gap-2 rounded-panel border border-garis bg-permukaan p-4"
            noValidate
        >
            <div className="w-full max-w-sm">
                <BidangTeks
                    label="Nama merek"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                    maxLength={150}
                    autoFocus
                    required
                />
            </div>
            <Tombol type="submit" memproses={formulir.processing}>
                Simpan merek
            </Tombol>
            <Tombol varian="sekunder" onClick={saatSelesai}>
                Batal
            </Tombol>
        </form>
    );
}

import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import KartuKodeAktivasi from '@/Komponen/Kelola/KartuKodeAktivasi';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { CekBatasPenuh, FormatBatas, IzinTenant, PunyaIzinTenant, type Batas, type Pilihan } from '@/Tipe/Organisasi';
import type { KodeAktivasiBaru } from '@/Tipe/PanduanAwal';

type StatusPerangkat = 'Aktif' | 'BelumDiaktifkan' | 'Dicabut';

type Perangkat = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Jenis: string;
    LabelJenis: string;
    UuidOutlet: string | null;
    NamaOutlet: string | null;
    Status: StatusPerangkat;
    Platform: string | null;
    VersiAplikasi: string | null;
    DiaktifkanPada: string | null;
    TerakhirAktifPada: string | null;
    DicabutPada: string | null;
};

type Outlet = { Uuid: string; Kode: string; Nama: string; BatasPerangkat: Batas };

type PropsDaftar = {
    Perangkat: Perangkat[];
    Outlet: Outlet[];
    JenisPerangkat: Pilihan[];
    KodeAktivasiBaru: KodeAktivasiBaru | null;
};

const labelStatus: Record<StatusPerangkat, { jenis: 'sukses' | 'peringatan' | 'netral'; teks: string }> = {
    Aktif: { jenis: 'sukses', teks: 'Aktif' },
    BelumDiaktifkan: { jenis: 'peringatan', teks: 'Belum diaktifkan' },
    Dicabut: { jenis: 'netral', teks: 'Dicabut' },
};

/** Perangkat POS per outlet: tambah, aktivasi lewat kode + QR, cabut (F-02 langkah 5, BR-02.1, BR-02.3). */
export default function HalamanDaftarPerangkat({ Perangkat, Outlet, JenisPerangkat, KodeAktivasiBaru }: PropsDaftar) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.PerangkatKelola);
    const [formTerbuka, AturFormTerbuka] = useState(false);
    const [cabut, AturCabut] = useState<Perangkat | null>(null);
    const [ubah, AturUbah] = useState<Perangkat | null>(null);

    return (
        <TataLetakAplikasi judul="Perangkat">
            <p className="text-isi text-teks-sekunder">
                Setiap aplikasi kasir, layar dapur, atau perangkat gudang didaftarkan di sini lalu diaktifkan dengan
                kode 8 karakter atau QR. Kode perangkat dipakai untuk nomor transaksi saat offline.
            </p>

            {KodeAktivasiBaru ? <KartuKodeAktivasi kode={KodeAktivasiBaru} /> : null}

            {Outlet.length > 0 ? (
                <ul className="flex flex-wrap gap-2 text-label text-teks-sekunder">
                    {Outlet.map((outlet) => (
                        <li key={outlet.Uuid} className="rounded-kontrol border border-garis bg-permukaan px-3 py-1">
                            {outlet.Nama}:{' '}
                            <span className="font-semibold text-teks-utama">
                                {FormatBatas(outlet.BatasPerangkat, 'perangkat')}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : null}

            {bolehKelola && Outlet.some((outlet) => CekBatasPenuh(outlet.BatasPerangkat)) ? (
                <Pemberitahuan jenis="info" judul="Batas perangkat paket tercapai di sebagian outlet">
                    Cabut perangkat yang tidak dipakai, atau tambah add-on perangkat di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    . Perangkat yang dicabut tidak dihitung.
                </Pemberitahuan>
            ) : null}

            {bolehKelola ? (
                formTerbuka ? (
                    <FormTambah outlet={Outlet} jenis={JenisPerangkat} saatSelesai={() => AturFormTerbuka(false)} />
                ) : (
                    <div>
                        <Tombol onClick={() => AturFormTerbuka(true)} disabled={Outlet.length === 0}>
                            Tambah perangkat
                        </Tombol>
                    </div>
                )
            ) : null}

            {ubah ? <FormUbahNama key={ubah.Uuid} perangkat={ubah} saatSelesai={() => AturUbah(null)} /> : null}
            {cabut ? <KonfirmasiCabut perangkat={cabut} saatSelesai={() => AturCabut(null)} /> : null}

            {Perangkat.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    Belum ada perangkat. Tambahkan perangkat kasir pertama untuk mulai berjualan.
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[860px] text-left text-isi">
                        <caption className="sr-only">Daftar perangkat</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Outlet
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Terakhir aktif
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Perangkat.map((baris) => (
                                <tr key={baris.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label text-teks-utama">{baris.Kode}</td>
                                    <td className="px-4 py-2 text-teks-utama">
                                        {baris.Nama}
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {baris.LabelJenis}
                                            {baris.Platform ? ` · ${baris.Platform}` : ''}
                                            {baris.VersiAplikasi ? ` · versi ${baris.VersiAplikasi}` : ''}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">{baris.NamaOutlet ?? '—'}</td>
                                    <td className="px-4 py-2">
                                        <LabelStatus
                                            jenis={labelStatus[baris.Status].jenis}
                                            teks={labelStatus[baris.Status].teks}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {FormatTanggalWaktu(baris.TerakhirAktifPada)}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {bolehKelola && baris.Status !== 'Dicabut' ? (
                                            <span className="flex justify-end gap-2">
                                                <Tombol varian="sekunder" onClick={() => AturUbah(baris)}>
                                                    Ubah nama
                                                </Tombol>
                                                <Tombol
                                                    varian="sekunder"
                                                    onClick={() =>
                                                        router.post(
                                                            `/kelola/perangkat/${baris.Uuid}/kode-aktivasi`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {baris.Status === 'Aktif'
                                                        ? 'Pindahkan ke HP lain'
                                                        : 'Buat kode baru'}
                                                </Tombol>
                                                <Tombol varian="bahaya" onClick={() => AturCabut(baris)}>
                                                    Cabut
                                                </Tombol>
                                            </span>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakAplikasi>
    );
}

type PropsFormTambah = { outlet: Outlet[]; jenis: Pilihan[]; saatSelesai: () => void };

function FormTambah({ outlet, jenis, saatSelesai }: PropsFormTambah) {
    const formulir = useForm({ Nama: '', Outlet: outlet[0]?.Uuid ?? '', Jenis: jenis[0]?.Nilai ?? 'Kasir' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/kelola/perangkat', { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid grid-cols-1 gap-4 rounded-panel border border-garis bg-permukaan p-4 md:grid-cols-3"
            noValidate
        >
            <BidangTeks
                label="Nama perangkat"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
                keterangan='Misal "Kasir Depan" atau "Tablet Dapur"'
                maxLength={100}
                autoFocus
                required
            />
            <BidangPilihan
                label="Outlet"
                nilai={formulir.data.Outlet}
                opsi={outlet.map((baris) => ({ Nilai: baris.Uuid, Label: `${baris.Nama} (${baris.Kode})` }))}
                saatBerubah={(nilai) => formulir.setData('Outlet', nilai)}
                galat={formulir.errors.Outlet}
            />
            <BidangPilihan
                label="Jenis"
                nilai={formulir.data.Jenis}
                opsi={jenis}
                saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                galat={formulir.errors.Jenis}
            />
            <div className="flex gap-2 md:col-span-3">
                <Tombol type="submit" memproses={formulir.processing}>
                    Tambah & buat kode aktivasi
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

function FormUbahNama({ perangkat, saatSelesai }: { perangkat: Perangkat; saatSelesai: () => void }) {
    const formulir = useForm({ Nama: perangkat.Nama });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(`/kelola/perangkat/${perangkat.Uuid}`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
            noValidate
        >
            <BidangTeks
                label={`Nama perangkat ${perangkat.Kode}`}
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
                maxLength={100}
                autoFocus
                required
            />
            <div className="flex gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan nama
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

function KonfirmasiCabut({ perangkat, saatSelesai }: { perangkat: Perangkat; saatSelesai: () => void }) {
    const [memproses, AturMemproses] = useState(false);

    return (
        <section
            role="alertdialog"
            aria-labelledby="judul-cabut"
            className="flex flex-col gap-3 rounded-panel border border-bahaya bg-permukaan p-4"
        >
            <h2 id="judul-cabut" className="text-subjudul font-semibold text-teks-utama">
                Cabut {perangkat.Nama} ({perangkat.Kode})?
            </h2>
            <p className="text-isi text-teks-sekunder">
                Aplikasi di perangkat ini langsung tidak bisa dipakai. Transaksi offline yang sudah dibuat sebelumnya
                tetap diterima saat sinkron untuk ditinjau. Pencabutan tidak bisa dibatalkan; kode {perangkat.Kode}{' '}
                tidak akan dipakai lagi.
            </p>
            <div className="flex gap-2">
                <Tombol
                    varian="bahaya"
                    memproses={memproses}
                    onClick={() =>
                        router.post(
                            `/kelola/perangkat/${perangkat.Uuid}/cabut`,
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => AturMemproses(true),
                                onFinish: () => AturMemproses(false),
                                onSuccess: saatSelesai,
                            },
                        )
                    }
                >
                    Cabut perangkat
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </section>
    );
}

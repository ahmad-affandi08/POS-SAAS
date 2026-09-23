import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabPengguna from '@/Komponen/Kelola/TabPengguna';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { CekBatasPenuh, FormatBatas, IzinTenant, PunyaIzinTenant, type Batas } from '@/Tipe/Organisasi';

type Anggota = {
    Uuid: string;
    Nama: string;
    Email: string;
    Pemilik: boolean;
    UuidPeran: string | null;
    NamaPeran: string | null;
    SemuaOutlet: boolean;
    UuidOutlet: string[];
    Status: 'Aktif' | 'Nonaktif';
    DinonaktifkanPada: string | null;
};

type Undangan = {
    Uuid: string;
    Email: string;
    NamaPeran: string | null;
    SemuaOutlet: boolean;
    JumlahOutlet: number;
    BerlakuSampai: string;
    Pengundang: string;
};

type Peran = { Uuid: string; Nama: string; Pemilik: boolean; SemuaOutletBawaan: boolean };

type Outlet = { Uuid: string; Kode: string; Nama: string };

type PropsDaftar = {
    Anggota: Anggota[];
    Undangan: Undangan[];
    Peran: Peran[];
    Outlet: Outlet[];
    BatasPengguna: Batas;
    UuidSaya: string;
};

type Pilihan = { jenis: 'akses' | 'nonaktifkan'; anggota: Anggota } | null;

/** Pengguna tenant: undang, atur peran & outlet, nonaktifkan (F-02 langkah 3, BR-02.1, BR-00.1). */
export default function HalamanDaftarPengguna({
    Anggota,
    Undangan,
    Peran,
    Outlet,
    BatasPengguna,
    UuidSaya,
}: PropsDaftar) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const akses = props.Akses;
    const bolehUndang = PunyaIzinTenant(akses, IzinTenant.PenggunaUndang);
    const bolehUbah = PunyaIzinTenant(akses, IzinTenant.PenggunaUbah);
    const bolehNonaktifkan = PunyaIzinTenant(akses, IzinTenant.PenggunaNonaktifkan);
    const sayaPemilik = akses?.Pemilik ?? false;
    const [formUndangan, AturFormUndangan] = useState(false);
    const [pilihan, AturPilihan] = useState<Pilihan>(null);
    const penuh = CekBatasPenuh(BatasPengguna);
    const peranTerlihat = Peran.filter((peran) => sayaPemilik || !peran.Pemilik);
    const namaOutlet = new Map(Outlet.map((outlet) => [outlet.Uuid, outlet.Kode]));

    // Pemilik hanya bisa diubah Pemilik lain; akun sendiri tidak bisa diubah dari sini.
    const BolehSentuh = (anggota: Anggota) => anggota.Uuid !== UuidSaya && (sayaPemilik || !anggota.Pemilik);

    return (
        <TataLetakAplikasi judul="Pengguna & peran">
            <TabPengguna />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Kursi pengguna (anggota aktif + undangan menunggu):{' '}
                    <span className="font-semibold text-teks-utama">{FormatBatas(BatasPengguna, 'pengguna')}</span>
                </p>
                {bolehUndang && !formUndangan ? (
                    <Tombol onClick={() => AturFormUndangan(true)} disabled={penuh}>
                        Undang pengguna
                    </Tombol>
                ) : null}
            </div>

            {bolehUndang && penuh ? (
                <Pemberitahuan jenis="info" judul="Batas pengguna paket sudah tercapai">
                    Nonaktifkan pengguna yang tidak dipakai, batalkan undangan, atau tingkatkan paket di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    .
                </Pemberitahuan>
            ) : null}

            {formUndangan ? (
                <FormAkses
                    judul="Undang pengguna"
                    alamat="/kelola/pengguna/undangan"
                    metode="post"
                    denganEmail
                    awal={{ Email: '', Peran: '', SemuaOutlet: false, Outlet: [] }}
                    peran={peranTerlihat}
                    outlet={Outlet}
                    tombol="Kirim undangan"
                    keterangan={`Undangan dikirim ke email, berlaku 72 jam, dan hanya bisa dipakai sekali. Bila email itu sudah punya akun (misal di usaha lain), akunnya ditautkan.`}
                    saatSelesai={() => AturFormUndangan(false)}
                />
            ) : null}

            {pilihan?.jenis === 'akses' ? (
                <FormAkses
                    key={pilihan.anggota.Uuid}
                    judul={`Peran & akses ${pilihan.anggota.Nama}`}
                    alamat={`/kelola/pengguna/${pilihan.anggota.Uuid}/akses`}
                    metode="put"
                    awal={{
                        Email: pilihan.anggota.Email,
                        Peran: pilihan.anggota.UuidPeran ?? '',
                        SemuaOutlet: pilihan.anggota.SemuaOutlet,
                        Outlet: pilihan.anggota.UuidOutlet,
                    }}
                    peran={peranTerlihat}
                    outlet={Outlet}
                    tombol="Simpan akses"
                    saatSelesai={() => AturPilihan(null)}
                />
            ) : null}
            {pilihan?.jenis === 'nonaktifkan' ? (
                <KonfirmasiNonaktifkan
                    key={pilihan.anggota.Uuid}
                    anggota={pilihan.anggota}
                    saatSelesai={() => AturPilihan(null)}
                />
            ) : null}

            <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                <table className="w-full min-w-[760px] text-left text-isi">
                    <caption className="sr-only">Daftar pengguna</caption>
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Nama
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Peran
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Outlet
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Status
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                <span className="sr-only">Aksi</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {Anggota.map((anggota) => (
                            <tr key={anggota.Uuid} className="border-b border-garis align-top last:border-b-0">
                                <td className="px-4 py-2">
                                    <p className="font-semibold text-teks-utama">
                                        {anggota.Nama}
                                        {anggota.Uuid === UuidSaya ? (
                                            <span className="font-normal text-teks-sekunder"> (Anda)</span>
                                        ) : null}
                                    </p>
                                    <p className="text-keterangan text-teks-sekunder">{anggota.Email}</p>
                                </td>
                                <td className="px-4 py-2 text-teks-utama">{anggota.NamaPeran ?? 'Belum ada peran'}</td>
                                <td className="px-4 py-2 text-teks-sekunder">
                                    {anggota.SemuaOutlet
                                        ? 'Semua outlet'
                                        : anggota.UuidOutlet.map((uuid) => namaOutlet.get(uuid) ?? 'Diarsipkan').join(
                                              ', ',
                                          ) || 'Belum ditugaskan'}
                                </td>
                                <td className="px-4 py-2">
                                    {anggota.Status === 'Aktif' ? (
                                        <LabelStatus jenis="sukses" teks="Aktif" />
                                    ) : (
                                        <LabelStatus
                                            jenis="netral"
                                            teks={`Nonaktif sejak ${FormatTanggalWaktu(anggota.DinonaktifkanPada)}`}
                                        />
                                    )}
                                </td>
                                <td className="px-4 py-2 text-right">
                                    {BolehSentuh(anggota) ? (
                                        <span className="flex justify-end gap-2">
                                            {anggota.Status === 'Aktif' && bolehUbah ? (
                                                <Tombol
                                                    varian="sekunder"
                                                    onClick={() => AturPilihan({ jenis: 'akses', anggota })}
                                                >
                                                    Ubah akses
                                                </Tombol>
                                            ) : null}
                                            {anggota.Status === 'Aktif' && bolehNonaktifkan ? (
                                                <Tombol
                                                    varian="bahaya"
                                                    onClick={() => AturPilihan({ jenis: 'nonaktifkan', anggota })}
                                                >
                                                    Nonaktifkan
                                                </Tombol>
                                            ) : null}
                                            {anggota.Status === 'Nonaktif' && bolehNonaktifkan ? (
                                                <Tombol
                                                    varian="sekunder"
                                                    disabled={penuh}
                                                    onClick={() =>
                                                        router.post(
                                                            `/kelola/pengguna/${anggota.Uuid}/aktifkan`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    Aktifkan kembali
                                                </Tombol>
                                            ) : null}
                                        </span>
                                    ) : null}
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
                            <li
                                key={undangan.Uuid}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-isi"
                            >
                                <span>
                                    <span className="font-semibold text-teks-utama">{undangan.Email}</span>
                                    <span className="block text-keterangan text-teks-sekunder">
                                        {undangan.NamaPeran ?? '—'} ·{' '}
                                        {undangan.SemuaOutlet
                                            ? 'semua outlet'
                                            : `${String(undangan.JumlahOutlet)} outlet`}{' '}
                                        · diundang {undangan.Pengundang} · berlaku sampai{' '}
                                        {FormatTanggalWaktu(undangan.BerlakuSampai)}
                                    </span>
                                </span>
                                {bolehUndang ? (
                                    <Tombol
                                        varian="sekunder"
                                        onClick={() =>
                                            router.post(
                                                `/kelola/pengguna/undangan/${undangan.Uuid}/batalkan`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Batalkan undangan
                                    </Tombol>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </TataLetakAplikasi>
    );
}

type IsianAkses = { Email: string; Peran: string; SemuaOutlet: boolean; Outlet: string[] };

type PropsFormAkses = {
    judul: string;
    alamat: string;
    metode: 'post' | 'put';
    denganEmail?: boolean;
    awal: IsianAkses;
    peran: Peran[];
    outlet: Outlet[];
    tombol: string;
    keterangan?: string;
    saatSelesai: () => void;
};

function FormAkses({
    judul,
    alamat,
    metode,
    denganEmail = false,
    awal,
    peran,
    outlet,
    tombol,
    keterangan,
    saatSelesai,
}: PropsFormAkses) {
    const formulir = useForm<IsianAkses>(awal);
    const peranTerpilih = peran.find((baris) => baris.Uuid === formulir.data.Peran);
    const semuaOutletPaksa = peranTerpilih?.Pemilik ?? false;

    const PilihPeran = (uuid: string) => {
        const baris = peran.find((item) => item.Uuid === uuid);
        formulir.setData({
            ...formulir.data,
            Peran: uuid,
            SemuaOutlet:
                baris?.Pemilik === true ||
                (metode === 'post' ? (baris?.SemuaOutletBawaan ?? false) : formulir.data.SemuaOutlet),
        });
    };

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.submit(metode, alamat, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
            {keterangan ? <p className="text-isi text-teks-sekunder">{keterangan}</p> : null}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {denganEmail ? (
                    <BidangTeks
                        label="Email"
                        jenis="email"
                        nilai={formulir.data.Email}
                        saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                        galat={formulir.errors.Email}
                        maxLength={191}
                        autoFocus
                        required
                    />
                ) : null}
                <BidangPilihan
                    label="Peran"
                    nilai={formulir.data.Peran}
                    opsi={peran.map((baris) => ({ Nilai: baris.Uuid, Label: baris.Nama }))}
                    saatBerubah={PilihPeran}
                    galat={formulir.errors.Peran}
                    kosong="Pilih peran"
                />
            </div>
            <KotakCentang
                label={
                    semuaOutletPaksa
                        ? 'Semua outlet (Pemilik selalu mengakses semua outlet)'
                        : 'Semua outlet, termasuk outlet baru'
                }
                nilai={formulir.data.SemuaOutlet || semuaOutletPaksa}
                saatBerubah={(nilai) => formulir.setData('SemuaOutlet', nilai)}
            />
            {formulir.errors.SemuaOutlet ? (
                <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.SemuaOutlet}</p>
            ) : null}
            {!formulir.data.SemuaOutlet && !semuaOutletPaksa ? (
                <GrupCentang
                    legenda="Outlet yang ditugaskan"
                    opsi={outlet.map((baris) => ({ nilai: baris.Uuid, label: `${baris.Kode} · ${baris.Nama}` }))}
                    terpilih={formulir.data.Outlet}
                    saatBerubah={(terpilih) => formulir.setData('Outlet', terpilih)}
                    galat={formulir.errors.Outlet}
                />
            ) : null}
            <div className="flex gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    {tombol}
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

function KonfirmasiNonaktifkan({ anggota, saatSelesai }: { anggota: Anggota; saatSelesai: () => void }) {
    const [memproses, AturMemproses] = useState(false);

    return (
        <div className="flex flex-col gap-3 rounded-panel border border-bahaya bg-permukaan p-6">
            <h2 className="text-subjudul font-semibold text-teks-utama">Nonaktifkan {anggota.Nama}?</h2>
            <p className="text-isi text-teks-sekunder">
                {anggota.Nama} langsung keluar dari usaha ini dan tidak bisa memilihnya lagi. Akun & riwayatnya tetap
                tersimpan, dan bisa diaktifkan kembali kapan saja. Undangan yang ia kirim ikut dibatalkan.
            </p>
            <div className="flex gap-2">
                <Tombol
                    varian="bahaya"
                    memproses={memproses}
                    onClick={() =>
                        router.post(
                            `/kelola/pengguna/${anggota.Uuid}/nonaktifkan`,
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
                    Nonaktifkan pengguna
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </div>
    );
}

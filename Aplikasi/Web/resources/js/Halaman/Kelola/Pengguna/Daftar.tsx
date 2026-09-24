import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabPengguna from '@/Komponen/Kelola/TabPengguna';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import { ItemAksiBaris, type AksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
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

/** Kolom daftar pengguna; kode outlet & akun sendiri dari props halaman. */
function BuatKolom(namaOutlet: Map<string, string>, uuidSaya: string): KolomTabel<Anggota>[] {
    return [
        {
            id: 'Nama',
            accessorFn: (anggota) => `${anggota.Nama} ${anggota.Email}`,
            header: 'Nama',
            meta: { label: 'Nama', prioritas: 'utama', wajib: true },
            cell: ({ row: { original: anggota } }) => (
                <>
                    <span className="block font-semibold text-teks-utama">
                        {anggota.Nama}
                        {anggota.Uuid === uuidSaya ? (
                            <span className="font-normal text-teks-sekunder"> (Anda)</span>
                        ) : null}
                    </span>
                    <span className="block text-keterangan break-all text-teks-sekunder">{anggota.Email}</span>
                </>
            ),
        },
        {
            id: 'NamaPeran',
            accessorFn: (anggota) => anggota.NamaPeran ?? 'Belum ada peran',
            header: 'Peran',
            meta: { label: 'Peran', prioritas: 'penting' },
        },
        {
            id: 'Outlet',
            header: 'Outlet',
            enableSorting: false,
            meta: { label: 'Outlet', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
            cell: ({ row: { original: anggota } }) =>
                anggota.SemuaOutlet
                    ? 'Semua outlet'
                    : anggota.UuidOutlet.map((uuid) => namaOutlet.get(uuid) ?? 'Diarsipkan').join(', ') ||
                      'Belum ditugaskan',
        },
        {
            id: 'Status',
            accessorKey: 'Status',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row: { original: anggota } }) =>
                anggota.Status === 'Aktif' ? (
                    <LabelStatus jenis="sukses" teks="Aktif" />
                ) : (
                    <LabelStatus
                        jenis="netral"
                        teks={`Nonaktif sejak ${FormatTanggalWaktu(anggota.DinonaktifkanPada)}`}
                    />
                ),
        },
    ];
}

const kolomUndangan: KolomTabel<Undangan>[] = [
    {
        id: 'Email',
        accessorKey: 'Email',
        header: 'Email',
        meta: { label: 'Email', prioritas: 'utama', wajib: true, kelasSel: 'break-all font-semibold text-teks-utama' },
    },
    {
        id: 'NamaPeran',
        accessorFn: (undangan) => undangan.NamaPeran ?? '—',
        header: 'Peran',
        meta: { label: 'Peran', prioritas: 'penting' },
    },
    {
        id: 'Outlet',
        header: 'Outlet',
        enableSorting: false,
        meta: { label: 'Outlet', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        cell: ({ row: { original: undangan } }) =>
            undangan.SemuaOutlet ? 'Semua outlet' : `${String(undangan.JumlahOutlet)} outlet`,
    },
    {
        id: 'Pengundang',
        accessorKey: 'Pengundang',
        header: 'Diundang oleh',
        meta: { label: 'Diundang oleh', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
    },
    {
        id: 'BerlakuSampai',
        accessorKey: 'BerlakuSampai',
        header: 'Berlaku sampai',
        meta: { label: 'Berlaku sampai', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggalWaktu(row.original.BerlakuSampai),
    },
];

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
    const kolom = useMemo(
        () => BuatKolom(new Map(Outlet.map((outlet) => [outlet.Uuid, outlet.Kode])), UuidSaya),
        [Outlet, UuidSaya],
    );

    // Pemilik hanya bisa diubah Pemilik lain; akun sendiri tidak bisa diubah dari sini.
    const BolehSentuh = (anggota: Anggota) => anggota.Uuid !== UuidSaya && (sayaPemilik || !anggota.Pemilik);

    const SusunAksi = (anggota: Anggota): AksiBaris[] => {
        if (!BolehSentuh(anggota)) {
            return [];
        }

        const aksi: AksiBaris[] = [];
        if (anggota.Status === 'Aktif' && bolehUbah) {
            aksi.push({ label: 'Ubah akses', saatPilih: () => AturPilihan({ jenis: 'akses', anggota }) });
        }
        if (anggota.Status === 'Aktif' && bolehNonaktifkan) {
            aksi.push({
                label: 'Nonaktifkan',
                bahaya: true,
                saatPilih: () => AturPilihan({ jenis: 'nonaktifkan', anggota }),
            });
        }
        if (anggota.Status === 'Nonaktif' && bolehNonaktifkan) {
            aksi.push({
                label: 'Aktifkan kembali',
                nonaktif: penuh,
                saatPilih: () => router.post(`/kelola/pengguna/${anggota.Uuid}/aktifkan`, {}, { preserveScroll: true }),
            });
        }

        return aksi;
    };

    return (
        <TataLetakAplikasi judul="Pengguna & peran">
            <TabPengguna />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Kursi pengguna (anggota aktif + undangan menunggu):{' '}
                    <span className="font-semibold text-teks-utama">{FormatBatas(BatasPengguna, 'pengguna')}</span>
                </p>
                {bolehUndang ? (
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

            <TabelData
                id="organisasi-pengguna"
                label="Daftar pengguna"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Anggota }}
                ambilIdBaris={(anggota) => anggota.Uuid}
                cari="Cari nama atau email"
                saring={[
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihan',
                        opsi: [
                            { nilai: 'Aktif', label: 'Aktif' },
                            { nilai: 'Nonaktif', label: 'Nonaktif' },
                        ],
                    },
                ]}
                labelBaris={(anggota) => `untuk ${anggota.Nama}`}
                aksiBaris={(anggota) => {
                    const aksi = SusunAksi(anggota);

                    return aksi.length === 0 ? null : <ItemAksiBaris aksi={aksi} />;
                }}
                kosong={{ judul: 'Belum ada pengguna lain. Undang pengguna agar tim bisa ikut bekerja.' }}
            />

            <section className="flex flex-col gap-2" aria-labelledby="judul-undangan">
                <h2 id="judul-undangan" className="text-subjudul font-semibold text-teks-utama">
                    Undangan menunggu
                </h2>
                <TabelData
                    id="organisasi-undangan"
                    label="Undangan menunggu"
                    kolom={kolomUndangan}
                    sumber={{ mode: 'lokal', data: Undangan }}
                    ambilIdBaris={(undangan) => undangan.Uuid}
                    labelBaris={(undangan) => `undangan ${undangan.Email}`}
                    {...(bolehUndang
                        ? {
                              aksiBaris: (undangan: Undangan) => (
                                  <DropdownMenuItem
                                      variant="destructive"
                                      onSelect={() =>
                                          router.post(
                                              `/kelola/pengguna/undangan/${undangan.Uuid}/batalkan`,
                                              {},
                                              { preserveScroll: true },
                                          )
                                      }
                                  >
                                      Batalkan undangan
                                  </DropdownMenuItem>
                              ),
                          }
                        : {})}
                    kosong={{ judul: 'Tidak ada undangan yang menunggu diterima.' }}
                />
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
        <DialogFormulir jenis="panel" judul={judul} keterangan={keterangan} saatTutup={saatSelesai}>
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
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
                <div className="flex flex-wrap gap-2">
                    <Tombol type="submit" memproses={formulir.processing}>
                        {tombol}
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}

function KonfirmasiNonaktifkan({ anggota, saatSelesai }: { anggota: Anggota; saatSelesai: () => void }) {
    const [memproses, AturMemproses] = useState(false);

    return (
        <DialogKonfirmasi
            judul={`Nonaktifkan ${anggota.Nama}?`}
            labelAksi="Nonaktifkan pengguna"
            memproses={memproses}
            saatBatal={saatSelesai}
            saatKonfirmasi={() =>
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
            <p>
                {anggota.Nama} langsung keluar dari usaha ini dan tidak bisa memilihnya lagi. Akun & riwayatnya tetap
                tersimpan, dan bisa diaktifkan kembali kapan saja. Undangan yang ia kirim ikut dibatalkan.
            </p>
        </DialogKonfirmasi>
    );
}

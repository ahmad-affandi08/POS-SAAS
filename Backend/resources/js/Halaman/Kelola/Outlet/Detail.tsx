import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import FormOutlet from '@/Komponen/Kelola/FormOutlet';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant, type Kota, type Pilihan, type StatusOrganisasi } from '@/Tipe/Organisasi';

type Outlet = {
    Uuid: string;
    Kode: string;
    Nama: string;
    UuidMerek: string | null;
    Alamat: string | null;
    KodeKota: string | null;
    ZonaWaktu: string;
    JamTutupBuku: string;
    Pkp: boolean;
    Nitku: string | null;
    PungutPbjt: boolean;
    Status: StatusOrganisasi;
    KodeTerkunci: boolean;
};

type Gudang = { Uuid: string; Kode: string; Nama: string; Jenis: string; Status: StatusOrganisasi };

type PropsDetail = { Outlet: Outlet; Gudang: Gudang[]; Merek: Pilihan[]; Kota: Kota[]; JenisGudang: Pilihan[] };

/** Profil outlet & lokasi stoknya (F-02 langkah 1–2, BR-02.2, BR-02.4). */
export default function HalamanDetailOutlet({ Outlet, Gudang, Merek, Kota, JenisGudang }: PropsDetail) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.OutletKelola);
    const alamat = `/kelola/outlet/${Outlet.Uuid}`;
    const namaKota = Kota.find((baris) => baris.Kode === Outlet.KodeKota)?.Nama ?? 'Belum diisi';

    return (
        <TataLetakAplikasi judul={Outlet.Nama}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="flex flex-wrap items-center gap-2 text-isi text-teks-sekunder">
                    <Link href="/kelola/outlet" className="font-semibold text-brand underline">
                        Semua outlet
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span className="font-mono text-label text-teks-utama">{Outlet.Kode}</span>
                    {Outlet.Status === 'Aktif' ? (
                        <LabelStatus jenis="sukses" teks="Aktif" />
                    ) : (
                        <LabelStatus jenis="netral" teks="Diarsipkan" />
                    )}
                </p>
                {bolehKelola ? (
                    Outlet.Status === 'Aktif' ? (
                        <Tombol
                            varian="bahaya"
                            onClick={() => router.post(`${alamat}/arsipkan`, {}, { preserveScroll: true })}
                        >
                            Arsipkan outlet
                        </Tombol>
                    ) : (
                        <Tombol onClick={() => router.post(`${alamat}/pulihkan`, {}, { preserveScroll: true })}>
                            Pulihkan outlet
                        </Tombol>
                    )
                ) : null}
            </div>

            {bolehKelola ? (
                <FormOutlet
                    uuid={Outlet.Uuid}
                    kodeTerkunci={Outlet.KodeTerkunci}
                    awal={{
                        Nama: Outlet.Nama,
                        Kode: Outlet.Kode,
                        Merek: Outlet.UuidMerek ?? '',
                        Alamat: Outlet.Alamat ?? '',
                        KodeKota: Outlet.KodeKota ?? '',
                        ZonaWaktu: Outlet.ZonaWaktu,
                        JamTutupBuku: Outlet.JamTutupBuku,
                        Pkp: Outlet.Pkp,
                        Nitku: Outlet.Nitku ?? '',
                        PungutPbjt: Outlet.PungutPbjt,
                    }}
                    merek={Merek}
                    kota={Kota}
                />
            ) : (
                <dl className="grid grid-cols-1 gap-3 rounded-panel border border-garis bg-permukaan p-6 text-isi md:grid-cols-2">
                    <Rincian label="Alamat" nilai={Outlet.Alamat ?? 'Belum diisi'} />
                    <Rincian label="Kabupaten/kota" nilai={`${namaKota} · ${Outlet.ZonaWaktu}`} />
                    <Rincian label="Jam tutup buku" nilai={Outlet.JamTutupBuku} />
                    <Rincian label="PKP" nilai={Outlet.Pkp ? 'Ya' : 'Tidak'} />
                </dl>
            )}

            <BagianGudang
                alamatOutlet={alamat}
                gudang={Gudang}
                jenis={JenisGudang}
                bolehKelola={bolehKelola && Outlet.Status === 'Aktif'}
            />
        </TataLetakAplikasi>
    );
}

function Rincian({ label, nilai }: { label: string; nilai: string }) {
    return (
        <div>
            <dt className="text-label text-teks-sekunder">{label}</dt>
            <dd className="text-teks-utama">{nilai}</dd>
        </div>
    );
}

type PropsBagianGudang = { alamatOutlet: string; gudang: Gudang[]; jenis: Pilihan[]; bolehKelola: boolean };

function BagianGudang({ alamatOutlet, gudang, jenis, bolehKelola }: PropsBagianGudang) {
    const [sunting, AturSunting] = useState<Gudang | 'baru' | null>(null);
    const labelJenis = new Map(jenis.map((baris) => [baris.Nilai, baris.Label]));

    return (
        <section className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-subjudul font-semibold text-teks-utama">Lokasi stok</h2>
                {bolehKelola && sunting === null ? (
                    <Tombol varian="sekunder" onClick={() => AturSunting('baru')}>
                        Tambah lokasi stok
                    </Tombol>
                ) : null}
            </div>
            <p className="text-keterangan text-teks-sekunder">
                Setiap outlet wajib punya minimal satu lokasi stok untuk barang jual. Tambah Dapur, Bar, atau Gudang
                Belakang bila stoknya dipisah.
            </p>
            {sunting !== null ? (
                <FormGudang
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    alamatOutlet={alamatOutlet}
                    gudang={sunting === 'baru' ? null : sunting}
                    jenis={jenis}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                <table className="w-full min-w-[640px] text-left text-isi">
                    <caption className="sr-only">Lokasi stok outlet</caption>
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Kode
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Nama
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Jenis
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
                        {gudang.map((baris) => (
                            <tr key={baris.Uuid} className="border-b border-garis last:border-b-0">
                                <td className="px-4 py-2 font-mono text-label text-teks-utama">{baris.Kode}</td>
                                <td className="px-4 py-2 text-teks-utama">{baris.Nama}</td>
                                <td className="px-4 py-2 text-teks-sekunder">
                                    {labelJenis.get(baris.Jenis) ?? baris.Jenis}
                                </td>
                                <td className="px-4 py-2">
                                    {baris.Status === 'Aktif' ? (
                                        <LabelStatus jenis="sukses" teks="Aktif" />
                                    ) : (
                                        <LabelStatus jenis="netral" teks="Diarsipkan" />
                                    )}
                                </td>
                                <td className="px-4 py-2 text-right">
                                    {bolehKelola ? (
                                        <span className="flex justify-end gap-2">
                                            <Tombol varian="sekunder" onClick={() => AturSunting(baris)}>
                                                Ubah
                                            </Tombol>
                                            <Tombol
                                                varian={baris.Status === 'Aktif' ? 'bahaya' : 'sekunder'}
                                                onClick={() =>
                                                    router.post(
                                                        `${alamatOutlet}/gudang/${baris.Uuid}/${baris.Status === 'Aktif' ? 'arsipkan' : 'pulihkan'}`,
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {baris.Status === 'Aktif' ? 'Arsipkan' : 'Pulihkan'}
                                            </Tombol>
                                        </span>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

type PropsFormGudang = { alamatOutlet: string; gudang: Gudang | null; jenis: Pilihan[]; saatSelesai: () => void };

function FormGudang({ alamatOutlet, gudang, jenis, saatSelesai }: PropsFormGudang) {
    const formulir = useForm({ Nama: gudang?.Nama ?? '', Kode: gudang?.Kode ?? '', Jenis: gudang?.Jenis ?? 'Gudang' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (gudang === null) {
            formulir.post(`${alamatOutlet}/gudang`, opsi);
        } else {
            formulir.put(`${alamatOutlet}/gudang/${gudang.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid grid-cols-1 gap-4 rounded-panel border border-garis bg-permukaan p-4 md:grid-cols-3"
            noValidate
        >
            <BidangTeks
                label="Nama lokasi"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
                maxLength={150}
                autoFocus
                required
            />
            <BidangTeks
                label="Kode"
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
                keterangan="Misal JKT1-DPR"
                maxLength={20}
                kode
                required
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
                    Simpan lokasi stok
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

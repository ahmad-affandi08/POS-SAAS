import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import FormOutlet from '@/Komponen/Kelola/FormOutlet';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import MenuAksiBaris from '@/Komponen/Tindakan/MenuAksiBaris';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
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

const kelasKepala = 'px-4 text-label font-semibold text-teks-sekunder';

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
                <DialogFormulir jenis="panel" judul="Tambah outlet" saatTutup={() => AturFormTerbuka(false)}>
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
                </DialogFormulir>
            ) : null}

            {Outlet.length === 0 ? (
                <Empty className="border border-garis bg-permukaan p-6 md:p-6">
                    <EmptyHeader>
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            Belum ada outlet yang bisa Anda akses. Minta Owner menugaskan Anda ke outlet.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="gap-0 py-0">
                    <Table className="min-w-[820px] text-isi">
                        <TableCaption className="sr-only">Daftar outlet</TableCaption>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="col" className={kelasKepala}>
                                    Kode
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Outlet
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Kota
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Tutup buku
                                </TableHead>
                                <TableHead scope="col" className={`${kelasKepala} text-right`}>
                                    Lokasi stok
                                </TableHead>
                                <TableHead scope="col" className={kelasKepala}>
                                    Status
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Outlet.map((outlet) => (
                                <TableRow key={outlet.Uuid}>
                                    <TableCell className="px-4 font-mono text-label text-teks-utama">
                                        {outlet.Kode}
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal">
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
                                    </TableCell>
                                    <TableCell className="px-4 whitespace-normal text-teks-sekunder">
                                        {outlet.NamaKota ?? 'Belum diisi'} · {outlet.ZonaWaktu}
                                    </TableCell>
                                    <TableCell className="px-4 font-mono text-label text-teks-sekunder">
                                        {outlet.JamTutupBuku}
                                    </TableCell>
                                    <TableCell className="px-4 text-right tabular-nums text-teks-utama">
                                        {outlet.JumlahGudang}
                                    </TableCell>
                                    <TableCell className="px-4">
                                        {outlet.Status === 'Aktif' ? (
                                            <LabelStatus jenis="sukses" teks="Aktif" />
                                        ) : (
                                            <LabelStatus jenis="netral" teks="Diarsipkan" />
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
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
                <DialogFormulir
                    judul={sunting === 'baru' ? 'Tambah merek' : `Ganti nama merek ${sunting.Nama}`}
                    saatTutup={() => AturSunting(null)}
                >
                    <FormMerek
                        key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                        merek={sunting === 'baru' ? null : sunting}
                        saatSelesai={() => AturSunting(null)}
                    />
                </DialogFormulir>
            ) : null}
            <Card className="gap-0 py-0">
                <ul className="divide-y divide-garis">
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
                                <MenuAksiBaris
                                    label={`Aksi merek ${baris.Nama}`}
                                    aksi={[
                                        { label: 'Ganti nama', saatPilih: () => AturSunting(baris) },
                                        ...(baris.JumlahOutlet === 0 && merek.length > 1
                                            ? [
                                                  {
                                                      label: 'Hapus merek',
                                                      bahaya: true,
                                                      saatPilih: () =>
                                                          router.delete(`/kelola/merek/${baris.Uuid}`, {
                                                              preserveScroll: true,
                                                          }),
                                                  },
                                              ]
                                            : []),
                                    ]}
                                />
                            ) : null}
                        </li>
                    ))}
                </ul>
            </Card>
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
        <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
            <BidangTeks
                label="Nama merek"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
                maxLength={150}
                autoFocus
                required
            />
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan merek
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

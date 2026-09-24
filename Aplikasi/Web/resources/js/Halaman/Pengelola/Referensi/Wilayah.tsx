import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Pengelola/DialogFormulir';
import KeadaanKosong from '@/Komponen/Pengelola/KeadaanKosong';
import PanelTabel from '@/Komponen/Pengelola/PanelTabel';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import {
    IzinPengelola,
    PunyaIzin,
    type DaftarBerhalaman,
    type Pilihan,
    type PropsBersamaPengelola,
} from '@/Tipe/Pengelola';

type Wilayah = { Kode: string; Nama: string; Tingkat: string; KodeInduk: string | null; ZonaWaktu: string };

type PropsWilayah = {
    Wilayah: DaftarBerhalaman<Wilayah>;
    Saring: { Kata: string; Tingkat: string | null };
    PilihanTingkat: Pilihan[];
    PilihanZonaWaktu: string[];
};

/** Data wilayah resmi (P-02). Muat massal lewat perintah server `pengelola:impor-wilayah`. */
export default function HalamanWilayah({ Wilayah, Saring, PilihanTingkat, PilihanZonaWaktu }: PropsWilayah) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiWilayahKelola);
    const [kata, AturKata] = useState(Saring.Kata);
    const [tingkat, AturTingkat] = useState(Saring.Tingkat ?? '');
    const [sunting, AturSunting] = useState<Wilayah | 'baru' | null>(null);
    const labelTingkat = new Map(PilihanTingkat.map((item) => [item.Nilai, item.Label]));
    const saringAktif = {
        ...(Saring.Kata ? { kata: Saring.Kata } : {}),
        ...(Saring.Tingkat ? { 'saring[Tingkat]': Saring.Tingkat } : {}),
    };

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get(
            '/referensi/wilayah',
            { ...(kata ? { kata } : {}), ...(tingkat ? { saring: { Tingkat: tingkat } } : {}) },
            { preserveState: true },
        );
    };

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah wilayah</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormWilayah
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    wilayah={sunting === 'baru' ? null : sunting}
                    pilihanTingkat={PilihanTingkat}
                    pilihanZonaWaktu={PilihanZonaWaktu}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            <form onSubmit={Cari} className="flex flex-wrap items-end gap-2">
                <div className="w-full max-w-xs">
                    <BidangTeks label="Cari nama atau kode" nilai={kata} saatBerubah={AturKata} />
                </div>
                <div className="w-48">
                    <BidangPilihan
                        label="Tingkat"
                        nilai={tingkat}
                        opsi={PilihanTingkat}
                        saatBerubah={AturTingkat}
                        kosong="Semua"
                    />
                </div>
                <Tombol type="submit" varian="sekunder">
                    Cari
                </Tombol>
            </form>

            {Wilayah.Data.length === 0 ? (
                <KeadaanKosong judul="Belum ada wilayah">
                    {Saring.Kata || Saring.Tingkat
                        ? 'Tidak ada wilayah yang cocok dengan pencarian.'
                        : 'Muat data resmi dengan perintah server: php artisan pengelola:impor-wilayah wilayah.csv'}
                </KeadaanKosong>
            ) : (
                <PanelTabel keterangan="Daftar wilayah">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Kode</TableHead>
                            <TableHead scope="col">Nama</TableHead>
                            <TableHead scope="col">Tingkat</TableHead>
                            <TableHead scope="col">Zona waktu</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Wilayah.Data.map((wilayah) => (
                            <TableRow key={wilayah.Kode}>
                                <TableCell className="font-mono text-label">{wilayah.Kode}</TableCell>
                                <TableCell className="text-teks-utama">{wilayah.Nama}</TableCell>
                                <TableCell className="text-teks-sekunder">
                                    {labelTingkat.get(wilayah.Tingkat) ?? wilayah.Tingkat}
                                </TableCell>
                                <TableCell className="text-teks-sekunder">{wilayah.ZonaWaktu}</TableCell>
                                <TableCell className="text-right">
                                    {bolehKelola ? (
                                        <Button variant="outline" size="sm" onClick={() => AturSunting(wilayah)}>
                                            Ubah
                                        </Button>
                                    ) : null}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </PanelTabel>
            )}
            <Paginasi
                alamat="/referensi/wilayah"
                saring={saringAktif}
                halamanSaatIni={Wilayah.HalamanSaatIni}
                halamanTerakhir={Wilayah.HalamanTerakhir}
                total={Wilayah.Total}
                label="Halaman wilayah"
            />
        </TataLetakPengelola>
    );
}

type PropsFormWilayah = {
    wilayah: Wilayah | null;
    pilihanTingkat: Pilihan[];
    pilihanZonaWaktu: string[];
    saatSelesai: () => void;
};

function FormWilayah({ wilayah, pilihanTingkat, pilihanZonaWaktu, saatSelesai }: PropsFormWilayah) {
    const formulir = useForm({
        Kode: wilayah?.Kode ?? '',
        Nama: wilayah?.Nama ?? '',
        Tingkat: wilayah?.Tingkat ?? 'KabupatenKota',
        KodeInduk: wilayah?.KodeInduk ?? '',
        ZonaWaktu: wilayah?.ZonaWaktu ?? 'WIB',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (wilayah === null) {
            formulir.post('/referensi/wilayah', opsi);
        } else {
            formulir.put(`/referensi/wilayah/${encodeURIComponent(wilayah.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={wilayah === null ? 'Tambah wilayah' : `Ubah ${wilayah.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode resmi"
                    kode
                    keterangan="Provinsi 2 digit (33), kabupaten/kota 33.74. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                    galat={formulir.errors.Kode}
                    disabled={wilayah !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangPilihan
                    label="Tingkat"
                    nilai={formulir.data.Tingkat}
                    opsi={pilihanTingkat}
                    saatBerubah={(nilai) => formulir.setData('Tingkat', nilai)}
                    galat={formulir.errors.Tingkat}
                />
                <BidangTeks
                    label="Kode provinsi induk"
                    kode
                    keterangan="Kosongkan untuk provinsi."
                    nilai={formulir.data.KodeInduk}
                    saatBerubah={(nilai) => formulir.setData('KodeInduk', nilai)}
                    galat={formulir.errors.KodeInduk}
                />
                <BidangPilihan
                    label="Zona waktu"
                    nilai={formulir.data.ZonaWaktu}
                    opsi={pilihanZonaWaktu.map((zona) => ({ Nilai: zona, Label: zona }))}
                    saatBerubah={(nilai) => formulir.setData('ZonaWaktu', nilai)}
                    galat={formulir.errors.ZonaWaktu}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan wilayah
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

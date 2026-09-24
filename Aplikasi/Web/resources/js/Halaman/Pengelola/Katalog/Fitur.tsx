import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Pengelola/DialogFormulir';
import KeadaanKosong from '@/Komponen/Pengelola/KeadaanKosong';
import PanelTabel from '@/Komponen/Pengelola/PanelTabel';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Fitur = { Kunci: string; Nama: string; Modul: string; Keterangan: string | null };

/** Katalog fitur (P-04). Kunci fitur dipakai kode aplikasi dan tidak bisa diubah. */
export default function HalamanFitur({ Fitur }: { Fitur: Fitur[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.KatalogFiturKelola);
    const [sunting, AturSunting] = useState<Fitur | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Katalog"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah fitur</Tombol>
                ) : null
            }
        >
            <TabKatalog />
            {sunting !== null ? (
                <FormFitur
                    key={sunting === 'baru' ? 'baru' : sunting.Kunci}
                    fitur={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {Fitur.length === 0 ? (
                <KeadaanKosong judul="Belum ada fitur">
                    Tambahkan fitur pertama. Kunci fitur dipakai kode aplikasi, jadi tulis sesuai modul yang sudah
                    dibangun.
                </KeadaanKosong>
            ) : (
                <PanelTabel keterangan="Katalog fitur">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Kunci</TableHead>
                            <TableHead scope="col">Nama</TableHead>
                            <TableHead scope="col">Modul</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Fitur.map((fitur) => (
                            <TableRow key={fitur.Kunci}>
                                <TableCell className="font-mono text-label">{fitur.Kunci}</TableCell>
                                <TableCell className="text-teks-utama">
                                    {fitur.Nama}
                                    {fitur.Keterangan ? (
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {fitur.Keterangan}
                                        </span>
                                    ) : null}
                                </TableCell>
                                <TableCell className="text-teks-sekunder">{fitur.Modul}</TableCell>
                                <TableCell className="text-right">
                                    {bolehKelola ? (
                                        <Button variant="outline" size="sm" onClick={() => AturSunting(fitur)}>
                                            Ubah
                                        </Button>
                                    ) : null}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </PanelTabel>
            )}
        </TataLetakPengelola>
    );
}

function FormFitur({ fitur, saatSelesai }: { fitur: Fitur | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Kunci: fitur?.Kunci ?? '',
        Nama: fitur?.Nama ?? '',
        Modul: fitur?.Modul ?? '',
        Keterangan: fitur?.Keterangan ?? '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (fitur === null) {
            formulir.post('/katalog/fitur', opsi);
        } else {
            formulir.put(`/katalog/fitur/${encodeURIComponent(fitur.Kunci)}`, opsi);
        }
    };

    return (
        <DialogFormulir judul={fitur === null ? 'Tambah fitur' : `Ubah ${fitur.Nama}`} saatTutup={saatSelesai}>
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kunci"
                    kode
                    keterangan="Huruf kecil dipisah titik, misal pos.mode-meja. Tidak bisa diubah."
                    nilai={formulir.data.Kunci}
                    saatBerubah={(nilai) => formulir.setData('Kunci', nilai.toLowerCase())}
                    galat={formulir.errors.Kunci}
                    disabled={fitur !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangTeks
                    label="Modul"
                    nilai={formulir.data.Modul}
                    saatBerubah={(nilai) => formulir.setData('Modul', nilai)}
                    galat={formulir.errors.Modul}
                />
                <BidangTeks
                    label="Keterangan (opsional)"
                    nilai={formulir.data.Keterangan}
                    saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                    galat={formulir.errors.Keterangan}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan fitur
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

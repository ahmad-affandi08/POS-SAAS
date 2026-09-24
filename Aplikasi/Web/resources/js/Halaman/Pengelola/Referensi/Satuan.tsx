import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Pengelola/DialogFormulir';
import KeadaanKosong from '@/Komponen/Pengelola/KeadaanKosong';
import PanelTabel from '@/Komponen/Pengelola/PanelTabel';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Satuan = { Kode: string; Nama: string; Simbol: string; BolehDesimal: boolean; Aktif: boolean };

/** Satuan standar platform, disalin ke tenant oleh template sektor (P-02). */
export default function HalamanSatuan({ Satuan }: { Satuan: Satuan[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiSatuanKelola);
    const [sunting, AturSunting] = useState<Satuan | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah satuan</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormSatuan
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    satuan={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            {Satuan.length === 0 ? (
                <KeadaanKosong judul="Belum ada satuan standar">
                    Tambahkan satuan pertama, misal pcs atau kg.
                </KeadaanKosong>
            ) : (
                <PanelTabel keterangan="Daftar satuan standar">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Kode</TableHead>
                            <TableHead scope="col">Nama</TableHead>
                            <TableHead scope="col">Simbol</TableHead>
                            <TableHead scope="col">Jumlah desimal</TableHead>
                            <TableHead scope="col">Status</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Satuan.map((satuan) => (
                            <TableRow key={satuan.Kode}>
                                <TableCell className="font-mono text-label">{satuan.Kode}</TableCell>
                                <TableCell className="text-teks-utama">{satuan.Nama}</TableCell>
                                <TableCell className="font-mono text-label text-teks-sekunder">
                                    {satuan.Simbol}
                                </TableCell>
                                <TableCell className="text-teks-sekunder">
                                    {satuan.BolehDesimal ? 'Boleh (misal 1,5)' : 'Bilangan bulat'}
                                </TableCell>
                                <TableCell>
                                    <LabelStatus
                                        jenis={satuan.Aktif ? 'sukses' : 'netral'}
                                        teks={satuan.Aktif ? 'Aktif' : 'Nonaktif'}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    {bolehKelola ? (
                                        <Button variant="outline" size="sm" onClick={() => AturSunting(satuan)}>
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

function FormSatuan({ satuan, saatSelesai }: { satuan: Satuan | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Kode: satuan?.Kode ?? '',
        Nama: satuan?.Nama ?? '',
        Simbol: satuan?.Simbol ?? '',
        BolehDesimal: satuan?.BolehDesimal ?? false,
        Aktif: satuan?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (satuan === null) {
            formulir.post('/referensi/satuan', opsi);
        } else {
            formulir.put(`/referensi/satuan/${encodeURIComponent(satuan.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={satuan === null ? 'Tambah satuan' : `Ubah ${satuan.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-3" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    keterangan="Huruf besar, misal KG. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                    disabled={satuan !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangTeks
                    label="Simbol"
                    nilai={formulir.data.Simbol}
                    saatBerubah={(nilai) => formulir.setData('Simbol', nilai)}
                    galat={formulir.errors.Simbol}
                />
                <KotakCentang
                    label="Boleh jumlah desimal (misal 1,5 kg)"
                    nilai={formulir.data.BolehDesimal}
                    saatBerubah={(nilai) => formulir.setData('BolehDesimal', nilai)}
                />
                <KotakCentang
                    label="Aktif"
                    nilai={formulir.data.Aktif}
                    saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
                />
                <DialogFooter className="sm:col-span-3 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan satuan
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

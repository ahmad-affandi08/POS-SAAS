import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
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
import { IzinPengelola, PunyaIzin, type Pilihan, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Referensi = { Kode: string; Nama: string; Jenis: string; Aktif: boolean };

type PropsBank = { Referensi: Referensi[]; PilihanJenis: Pilihan[] };

/** Referensi pembayaran: bank, dompet digital, jaringan EDC, penerbit QRIS (P-02). */
export default function HalamanBank({ Referensi, PilihanJenis }: PropsBank) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiBankKelola);
    const [sunting, AturSunting] = useState<Referensi | 'baru' | null>(null);
    const labelJenis = new Map(PilihanJenis.map((item) => [item.Nilai, item.Label]));

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah referensi</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormBank
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    referensi={sunting === 'baru' ? null : sunting}
                    pilihanJenis={PilihanJenis}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            {Referensi.length === 0 ? (
                <KeadaanKosong judul="Belum ada referensi pembayaran">
                    Tambahkan bank, dompet digital, jaringan EDC, atau penerbit QRIS yang bisa dipilih tenant.
                </KeadaanKosong>
            ) : (
                <PanelTabel keterangan="Daftar referensi pembayaran">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Kode</TableHead>
                            <TableHead scope="col">Nama</TableHead>
                            <TableHead scope="col">Jenis</TableHead>
                            <TableHead scope="col">Status</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Referensi.map((referensi) => (
                            <TableRow key={referensi.Kode}>
                                <TableCell className="font-mono text-label">{referensi.Kode}</TableCell>
                                <TableCell className="text-teks-utama">{referensi.Nama}</TableCell>
                                <TableCell className="text-teks-sekunder">
                                    {labelJenis.get(referensi.Jenis) ?? referensi.Jenis}
                                </TableCell>
                                <TableCell>
                                    <LabelStatus
                                        jenis={referensi.Aktif ? 'sukses' : 'netral'}
                                        teks={referensi.Aktif ? 'Aktif' : 'Nonaktif'}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    {bolehKelola ? (
                                        <Button variant="outline" size="sm" onClick={() => AturSunting(referensi)}>
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

function FormBank({
    referensi,
    pilihanJenis,
    saatSelesai,
}: {
    referensi: Referensi | null;
    pilihanJenis: Pilihan[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        Kode: referensi?.Kode ?? '',
        Nama: referensi?.Nama ?? '',
        Jenis: referensi?.Jenis ?? 'Bank',
        Aktif: referensi?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (referensi === null) {
            formulir.post('/referensi/bank', opsi);
        } else {
            formulir.put(`/referensi/bank/${encodeURIComponent(referensi.Kode)}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={referensi === null ? 'Tambah referensi pembayaran' : `Ubah ${referensi.Nama}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    keterangan="Huruf besar tanpa spasi, misal BCA atau GOPAY. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                    disabled={referensi !== null}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangPilihan
                    label="Jenis"
                    nilai={formulir.data.Jenis}
                    opsi={pilihanJenis}
                    saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                    galat={formulir.errors.Jenis}
                />
                <KotakCentang
                    label="Aktif (bisa dipilih tenant)"
                    nilai={formulir.data.Aktif}
                    saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan referensi
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

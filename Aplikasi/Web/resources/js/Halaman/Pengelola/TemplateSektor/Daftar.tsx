import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import KeadaanKosong from '@/Komponen/Pengelola/KeadaanKosong';
import PanelTabel from '@/Komponen/Pengelola/PanelTabel';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type RingkasanTemplate = {
    Kode: string;
    Nama: string;
    Keterangan: string | null;
    VersiTerbit: { Versi: number; DiterbitkanPada: string | null } | null;
    VersiDraf: { Versi: number; Lolos: boolean; SudahDivalidasi: boolean } | null;
    VersiTerbaru: number | null;
};

/** Daftar template sektor berversi (P-03). */
export default function HalamanDaftarTemplateSektor({ Template }: { Template: RingkasanTemplate[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehBuat = PunyaIzin(props.Pengguna, IzinPengelola.TemplateIsiUbah);
    const [buatBaru, AturBuatBaru] = useState(false);

    return (
        <TataLetakPengelola
            judul="Template sektor"
            aksi={bolehBuat && !buatBaru ? <Tombol onClick={() => AturBuatBaru(true)}>Buat template</Tombol> : null}
        >
            <p className="text-isi text-teks-sekunder">
                Paket konfigurasi yang diterapkan saat tenant onboarding. Versi terbit tidak diubah; perbaikan dibuat
                sebagai draf versi baru. Tenant lama tidak berubah tanpa persetujuannya.
            </p>
            {buatBaru ? <FormBuatTemplate template={Template} saatSelesai={() => AturBuatBaru(false)} /> : null}
            {Template.length === 0 ? (
                <KeadaanKosong judul="Belum ada template sektor">
                    Buat template pertama, misal Retail umum (RTL-GEN).
                </KeadaanKosong>
            ) : (
                <PanelTabel keterangan="Daftar template sektor">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Template</TableHead>
                            <TableHead scope="col">Versi terbit</TableHead>
                            <TableHead scope="col">Draf</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Template.map((template) => (
                            <BarisTemplate key={template.Kode} template={template} />
                        ))}
                    </TableBody>
                </PanelTabel>
            )}
        </TataLetakPengelola>
    );
}

function BarisTemplate({ template }: { template: RingkasanTemplate }) {
    const versiBuka = template.VersiDraf?.Versi ?? template.VersiTerbit?.Versi ?? template.VersiTerbaru;

    return (
        <TableRow>
            <TableCell>
                <p className="font-semibold text-teks-utama">{template.Nama}</p>
                <p className="font-mono text-keterangan text-teks-sekunder">{template.Kode}</p>
                {template.Keterangan ? (
                    <p className="text-keterangan text-teks-sekunder">{template.Keterangan}</p>
                ) : null}
            </TableCell>
            <TableCell>
                {template.VersiTerbit ? (
                    <>
                        <p className="tabular-nums">Versi {template.VersiTerbit.Versi}</p>
                        <p className="text-keterangan text-teks-sekunder">
                            Terbit {FormatTanggal(template.VersiTerbit.DiterbitkanPada)}
                        </p>
                    </>
                ) : (
                    <LabelStatus jenis="peringatan" teks="Belum terbit" />
                )}
            </TableCell>
            <TableCell>
                {template.VersiDraf ? (
                    <div className="flex flex-col gap-1">
                        <span className="tabular-nums">Versi {template.VersiDraf.Versi}</span>
                        <LabelStatus
                            jenis={template.VersiDraf.Lolos ? 'sukses' : 'bahaya'}
                            teks={
                                template.VersiDraf.Lolos
                                    ? 'Lolos validasi'
                                    : template.VersiDraf.SudahDivalidasi
                                      ? 'Belum lolos validasi'
                                      : 'Belum divalidasi'
                            }
                        />
                    </div>
                ) : (
                    <span className="text-teks-sekunder">Tidak ada</span>
                )}
            </TableCell>
            <TableCell className="text-right">
                {versiBuka ? (
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/template-sektor/${encodeURIComponent(template.Kode)}/versi/${versiBuka}`}>
                            Buka
                        </Link>
                    </Button>
                ) : null}
            </TableCell>
        </TableRow>
    );
}

type PropsFormBuat = { template: RingkasanTemplate[]; saatSelesai: () => void };

function FormBuatTemplate({ template, saatSelesai }: PropsFormBuat) {
    const formulir = useForm({ Kode: '', Nama: '', Keterangan: '', KodeTemplateDasar: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/template-sektor', { preserveScroll: true });
    };

    return (
        <DialogFormulir
            judul="Buat template sektor"
            saatTutup={saatSelesai}
            lebar="lebar"
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode sektor"
                    kode
                    keterangan="Tiga huruf kelompok dan tiga huruf sektor, misal FNB-RST. Tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                />
                <BidangTeks
                    label="Nama"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                />
                <BidangTeks
                    label="Keterangan (opsional)"
                    nilai={formulir.data.Keterangan}
                    saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                    galat={formulir.errors.Keterangan}
                />
                <BidangPilihan
                    label="Salin isi dari"
                    nilai={formulir.data.KodeTemplateDasar}
                    kosong="Mulai kosong"
                    opsi={template.map((item) => ({ Nilai: item.Kode, Label: `${item.Nama} (${item.Kode})` }))}
                    saatBerubah={(nilai) => formulir.setData('KodeTemplateDasar', nilai)}
                    galat={formulir.errors.KodeTemplateDasar}
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Buat draf versi 1
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

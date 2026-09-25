import { Link, router, useForm } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RincianTagihan from '@/Komponen/Langganan/RincianTagihan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Komponen/Ui/alert-dialog';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Komponen/Ui/dialog';
import { Field, FieldDescription, FieldError } from '@/Komponen/Ui/field';
import { Input } from '@/Komponen/Ui/input';
import { Label } from '@/Komponen/Ui/label';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import { JenisLabelPembayaran, type PembayaranLangganan, type TagihanLangganan } from '@/Tipe/TagihanLangganan';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { TulisTanggal } from '@/Pustaka/Tanggal';

type Rekening = { Kode: string; NamaBank: string; NomorRekening: string; AtasNama: string };

type PropsTagihan = {
    Tagihan: TagihanLangganan;
    Pembayaran: PembayaranLangganan[];
    RekeningTujuan: Rekening[];
    BolehUnggah: boolean;
    UkuranBuktiMaksimalKb: number;
};

/** Detail tagihan, rekening tujuan, unggah bukti transfer, dan status verifikasi (P-08 langkah 3). */
export default function HalamanTagihanLangganan({
    Tagihan,
    Pembayaran,
    RekeningTujuan,
    BolehUnggah,
    UkuranBuktiMaksimalKb,
}: PropsTagihan) {
    const terbuka = Tagihan.Status === 'Terbit' || Tagihan.Status === 'JatuhTempo';
    const menunggu = Pembayaran.find((pembayaran) => pembayaran.Status === 'Menunggu');
    const [membatalkan, AturMembatalkan] = useState(false);

    const Batalkan = () =>
        router.post(
            `/kelola/langganan/tagihan/${Tagihan.Uuid}/batalkan`,
            {},
            { onStart: () => AturMembatalkan(true), onFinish: () => AturMembatalkan(false) },
        );

    return (
        <TataLetakAplikasi judul={`Tagihan ${Tagihan.Nomor}`}>
            <p>
                <Link href="/kelola/langganan" className="text-label font-semibold text-brand underline">
                    Kembali ke langganan
                </Link>
            </p>
            {menunggu ? (
                <Pemberitahuan jenis="info" judul="Bukti transfer sedang diverifikasi">
                    Kami memeriksa mutasi rekening pada hari kerja. Hasilnya dikirim ke email Anda.
                </Pemberitahuan>
            ) : null}
            {Tagihan.Status === 'Lunas' ? (
                <Pemberitahuan jenis="sukses" judul="Tagihan lunas">
                    Terima kasih. Langganan Anda sudah aktif sesuai periode di bawah.
                </Pemberitahuan>
            ) : null}
            <RincianTagihan tagihan={Tagihan} />
            {terbuka ? <DaftarRekening rekening={RekeningTujuan} total={Tagihan.Total} /> : null}
            {BolehUnggah ? (
                <div>
                    <DialogBukti tagihan={Tagihan} rekening={RekeningTujuan} ukuranMaksimalKb={UkuranBuktiMaksimalKb} />
                </div>
            ) : null}
            <RiwayatPembayaran pembayaran={Pembayaran} />
            {BolehUnggah ? (
                <div>
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="border-destructive text-destructive"
                                disabled={membatalkan}
                                aria-busy={membatalkan || undefined}
                            >
                                {membatalkan ? 'Memproses…' : 'Batalkan tagihan'}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Batalkan tagihan {Tagihan.Nomor}?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Anda bisa membuat tagihan baru setelahnya.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Jangan batalkan</AlertDialogCancel>
                                <AlertDialogAction variant="destructive" onClick={Batalkan}>
                                    Batalkan tagihan
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            ) : null}
        </TataLetakAplikasi>
    );
}

function DaftarRekening({ rekening, total }: { rekening: Rekening[]; total: string }) {
    return (
        <Card aria-labelledby="judul-rekening" role="region" className="gap-1 px-4 py-3">
            <h2 id="judul-rekening" className="text-subjudul font-semibold text-teks-utama">
                Transfer ke rekening berikut
            </h2>
            <p className="text-isi text-teks-sekunder">
                Transfer tepat <span className="font-semibold tabular-nums text-teks-utama">{FormatRupiah(total)}</span>{' '}
                agar verifikasi cepat.
            </p>
            {rekening.length === 0 ? (
                <p className="mt-2 text-isi text-bahaya">Rekening tujuan belum diatur. Hubungi tim kami.</p>
            ) : (
                <ul className="mt-2 flex flex-col gap-2">
                    {rekening.map((baris) => (
                        <li key={baris.Kode} className="text-isi">
                            <span className="font-semibold text-teks-utama">{baris.NamaBank}</span>{' '}
                            <span className="font-mono">{baris.NomorRekening}</span>{' '}
                            <span className="text-teks-sekunder">a.n. {baris.AtasNama}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

type PropsDialogBukti = {
    tagihan: TagihanLangganan;
    rekening: Rekening[];
    ukuranMaksimalKb: number;
};

/** Tombol "Unggah bukti transfer" + dialog formulirnya. Dialog tertutup sendiri setelah bukti terkirim. */
function DialogBukti({ tagihan, rekening, ukuranMaksimalKb }: PropsDialogBukti) {
    const [terbuka, AturTerbuka] = useState(false);

    return (
        <Dialog open={terbuka} onOpenChange={AturTerbuka}>
            <DialogTrigger asChild>
                <Button type="button">Unggah bukti transfer</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Unggah bukti transfer</DialogTitle>
                    <DialogDescription>
                        Tagihan {tagihan.Nomor}, total {FormatRupiah(tagihan.Total)}.
                    </DialogDescription>
                </DialogHeader>
                <FormBukti
                    tagihan={tagihan}
                    rekening={rekening}
                    ukuranMaksimalKb={ukuranMaksimalKb}
                    saatSelesai={() => AturTerbuka(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function FormBukti({
    tagihan,
    rekening,
    ukuranMaksimalKb,
    saatSelesai,
}: PropsDialogBukti & { saatSelesai: () => void }) {
    const idBerkas = useId();
    const formulir = useForm<{
        Bukti: File | null;
        Jumlah: string;
        TanggalTransfer: string;
        BankPengirim: string;
        NamaPengirim: string;
        KodeRekeningTujuan: string;
    }>({
        Bukti: null,
        Jumlah: tagihan.Total.replace(/\.00$/, ''),
        TanggalTransfer: '',
        BankPengirim: '',
        NamaPengirim: '',
        KodeRekeningTujuan: rekening[0]?.Kode ?? '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/kelola/langganan/tagihan/${tagihan.Uuid}/pembayaran`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                formulir.reset();
                saatSelesai();
            },
        });
    };

    return (
        <form onSubmit={Kirim} aria-label="Unggah bukti transfer" className="grid gap-4 sm:grid-cols-2" noValidate>
            <Field className="gap-1 sm:col-span-2" data-invalid={formulir.errors.Bukti ? true : undefined}>
                <Label htmlFor={idBerkas} className="text-label font-semibold text-teks-utama">
                    Bukti transfer
                </Label>
                <Input
                    id={idBerkas}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    onChange={(peristiwa) => formulir.setData('Bukti', peristiwa.target.files?.[0] ?? null)}
                    aria-invalid={formulir.errors.Bukti ? true : undefined}
                    aria-describedby={`${idBerkas}-keterangan`}
                    required
                />
                <FieldDescription id={`${idBerkas}-keterangan`} className="text-keterangan">
                    Foto atau PDF (JPG, PNG, WEBP, PDF), maksimal {Math.floor(ukuranMaksimalKb / 1024)} MB.
                </FieldDescription>
                {formulir.errors.Bukti ? (
                    <FieldError className="text-keterangan font-semibold">{formulir.errors.Bukti}</FieldError>
                ) : null}
            </Field>
            <BidangTeks
                label="Jumlah transfer (Rp)"
                inputMode="decimal"
                nilai={formulir.data.Jumlah}
                saatBerubah={(nilai) => formulir.setData('Jumlah', nilai)}
                galat={formulir.errors.Jumlah}
                required
                keterangan={`Harus sama dengan total ${FormatRupiah(tagihan.Total)}.`}
            />
            <PemilihTanggal
                label="Tanggal transfer"
                max={TulisTanggal(new Date())}
                nilai={formulir.data.TanggalTransfer}
                saatBerubah={(nilai) => formulir.setData('TanggalTransfer', nilai)}
                galat={formulir.errors.TanggalTransfer}
                required
            />
            <BidangTeks
                label="Bank pengirim"
                nilai={formulir.data.BankPengirim}
                saatBerubah={(nilai) => formulir.setData('BankPengirim', nilai)}
                galat={formulir.errors.BankPengirim}
                required
            />
            <BidangTeks
                label="Nama pemilik rekening pengirim"
                nilai={formulir.data.NamaPengirim}
                saatBerubah={(nilai) => formulir.setData('NamaPengirim', nilai)}
                galat={formulir.errors.NamaPengirim}
                required
            />
            {rekening.length > 1 ? (
                <BidangPilihan
                    label="Rekening tujuan"
                    nilai={formulir.data.KodeRekeningTujuan}
                    opsi={rekening.map((baris) => ({
                        Nilai: baris.Kode,
                        Label: `${baris.NamaBank} ${baris.NomorRekening}`,
                    }))}
                    saatBerubah={(nilai) => formulir.setData('KodeRekeningTujuan', nilai)}
                    galat={formulir.errors.KodeRekeningTujuan}
                    required
                />
            ) : null}
            <DialogFooter className="sm:col-span-2">
                <Button type="button" variant="outline" onClick={saatSelesai}>
                    Batal
                </Button>
                <Tombol type="submit" memproses={formulir.processing} disabled={formulir.data.Bukti === null}>
                    Kirim bukti transfer
                </Tombol>
            </DialogFooter>
        </form>
    );
}

const kolomPembayaran: KolomTabel<PembayaranLangganan>[] = [
    {
        id: 'DiunggahPada',
        accessorKey: 'DiunggahPada',
        header: 'Diunggah',
        meta: { label: 'Diunggah', prioritas: 'utama', wajib: true, kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggalWaktu(row.original.DiunggahPada),
    },
    {
        id: 'Transfer',
        header: 'Transfer',
        enableSorting: false,
        meta: { label: 'Transfer', prioritas: 'rendah' },
        cell: ({ row: { original: baris } }) => (
            <>
                <span className="block">{FormatTanggal(baris.TanggalTransfer)}</span>
                <span className="block text-keterangan text-teks-sekunder">
                    {baris.BankPengirim} · {baris.NamaPengirim}
                </span>
            </>
        ),
    },
    {
        id: 'Jumlah',
        header: 'Jumlah',
        enableSorting: false,
        meta: { label: 'Jumlah', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.Jumlah),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: baris } }) => (
            <>
                <LabelStatus jenis={JenisLabelPembayaran(baris.Status)} teks={baris.LabelStatus} />
                {baris.AlasanTolak ? (
                    <span className="mt-1 block text-keterangan text-teks-sekunder">Alasan: {baris.AlasanTolak}</span>
                ) : null}
            </>
        ),
    },
    {
        id: 'Bukti',
        header: () => <span className="sr-only">Bukti</span>,
        enableSorting: false,
        meta: { label: 'Bukti', prioritas: 'penting', wajib: true, kelasSel: 'text-right' },
        cell: ({ row }) => (
            <a
                href={`/kelola/langganan/pembayaran/${row.original.Uuid}/bukti`}
                target="_blank"
                rel="noreferrer"
                className="text-label font-semibold text-brand underline"
            >
                Lihat bukti
            </a>
        ),
    },
];

function RiwayatPembayaran({ pembayaran }: { pembayaran: PembayaranLangganan[] }) {
    if (pembayaran.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="judul-pembayaran" className="flex flex-col gap-2">
            <h2 id="judul-pembayaran" className="text-subjudul font-semibold text-teks-utama">
                Bukti transfer terkirim
            </h2>
            <TabelData
                id="langganan-riwayat-pembayaran"
                label="Riwayat bukti transfer"
                kolom={kolomPembayaran}
                sumber={{ mode: 'lokal', data: pembayaran }}
                ambilIdBaris={(baris) => baris.Uuid}
                urutBawaan="-DiunggahPada"
                kosong={{ judul: 'Belum ada bukti transfer.' }}
            />
        </section>
    );
}

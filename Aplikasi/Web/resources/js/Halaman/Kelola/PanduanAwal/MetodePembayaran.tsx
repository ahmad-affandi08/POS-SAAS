import { router, useForm } from '@inertiajs/react';
import { useRef, useState, type FormEvent } from 'react';

import BidangGambar from '@/Komponen/Formulir/BidangGambar';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
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
import { Card, CardContent, CardHeader } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatPersen } from '@/Pustaka/Format';
import { FormatMasukanPersen, NormalisasiMasukanPersen } from '@/Pustaka/MasukanUang';
import { AlamatPanduan, type MetodePembayaranRingkas, type PropsMetodePembayaranPanduan } from '@/Tipe/PanduanAwal';

type IsianMetodePembayaran = {
    Jenis: string;
    Nama: string;
    KodeBank: string;
    GambarQris: File | null;
    NomorRekening: string;
    NamaPemilikRekening: string;
    PersenBiaya: string;
};

type JenisBank = PropsMetodePembayaranPanduan['Bank'][number]['Jenis'];

// Edc → bank atau jaringan EDC; Transfer → bank atau e-wallet (DesainF01 §D SimpanMetodePembayaranPermintaan).
const jenisBankPerMetode: Record<string, JenisBank[]> = {
    Edc: ['Bank', 'JaringanEdc'],
    Transfer: ['Bank', 'Ewallet'],
};

const contohNama: Record<string, string> = {
    QrisStatis: 'Misal "QRIS Toko". Tampil sebagai tombol di kasir.',
    Edc: 'Misal "EDC BCA". Tampil sebagai tombol di kasir.',
    Transfer: 'Misal "Transfer BCA". Tampil sebagai tombol di kasir.',
};

/** Langkah 5 F-01: Tunai selalu ada; tambah QRIS statis, EDC per bank, atau transfer. */
export default function HalamanMetodePembayaran({
    Progres,
    MetodePembayaran,
    JenisTersedia,
    Bank,
    BatasGambarQris,
}: PropsMetodePembayaranPanduan) {
    const hanyaTunai = MetodePembayaran.every((metode) => metode.Wajib);

    return (
        <TataLetakPanduan progres={Progres} langkah="MetodePembayaran" lanjut="tandai-selesai">
            {hanyaTunai ? (
                <p className="text-isi text-teks-sekunder">
                    Saat ini kasir hanya menerima Tunai. Tambahkan QRIS, EDC, atau transfer bank yang Anda pakai supaya
                    kasir bisa mencatat pembayaran non-tunai dengan benar.
                </p>
            ) : (
                <p className="text-isi text-teks-sekunder">
                    Metode pembayaran aktif tampil sebagai tombol di layar bayar aplikasi kasir.
                </p>
            )}

            <TabelMetodePembayaran metodePembayaran={MetodePembayaran} />

            <FormTambahMetode jenisTersedia={JenisTersedia} bank={Bank} batasGambarQris={BatasGambarQris} />
        </TataLetakPanduan>
    );
}

function TabelMetodePembayaran({ metodePembayaran }: { metodePembayaran: MetodePembayaranRingkas[] }) {
    const [memproses, AturMemproses] = useState<string | null>(null);

    const UbahStatus = (metode: MetodePembayaranRingkas) =>
        router.post(
            `${AlamatPanduan.MetodePembayaran}/${metode.Uuid}/${metode.Aktif ? 'nonaktifkan' : 'aktifkan'}`,
            {},
            {
                preserveScroll: true,
                onStart: () => AturMemproses(metode.Uuid),
                onFinish: () => AturMemproses(null),
            },
        );

    if (metodePembayaran.length === 0) {
        return (
            <Empty className="items-start border border-solid border-garis bg-permukaan p-6 text-left md:p-6">
                <EmptyHeader className="max-w-none items-start text-left">
                    <EmptyDescription className="text-isi text-teks-sekunder">
                        Belum ada metode pembayaran yang tercatat. Tunai selalu tersedia di kasir.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <Card className="gap-0 py-0">
            <Table className="min-w-[720px] text-isi">
                <TableCaption className="sr-only">Metode pembayaran</TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col" className="px-4">
                            Nama
                        </TableHead>
                        <TableHead scope="col" className="px-4">
                            Rincian
                        </TableHead>
                        <TableHead scope="col" className="px-4 text-right">
                            Biaya
                        </TableHead>
                        <TableHead scope="col" className="px-4">
                            Status
                        </TableHead>
                        <TableHead scope="col" className="px-4">
                            <span className="sr-only">Aksi</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {metodePembayaran.map((metode) => (
                        <TableRow key={metode.Uuid} className="align-top">
                            <TableCell className="px-4 whitespace-normal text-teks-utama">
                                <span className="break-words">{metode.Nama}</span>
                                <span className="block text-keterangan text-teks-sekunder">{metode.LabelJenis}</span>
                            </TableCell>
                            <TableCell className="px-4 whitespace-normal text-teks-sekunder">
                                {metode.TautanGambarQris ? (
                                    <img
                                        src={metode.TautanGambarQris}
                                        alt={`Gambar QRIS ${metode.Nama}`}
                                        width={64}
                                        height={64}
                                        className="size-16 rounded-kontrol border border-garis object-contain"
                                    />
                                ) : null}
                                {metode.NamaBank ? <span className="block">{metode.NamaBank}</span> : null}
                                {metode.NomorRekening ? (
                                    <span className="block font-mono text-label">
                                        {metode.NomorRekening}
                                        {metode.NamaPemilikRekening ? ` a.n. ${metode.NamaPemilikRekening}` : ''}
                                    </span>
                                ) : null}
                                {!metode.TautanGambarQris && !metode.NamaBank && !metode.NomorRekening ? '—' : null}
                            </TableCell>
                            <TableCell className="px-4 text-right text-teks-utama tabular-nums">
                                {FormatPersen(metode.PersenBiaya)}%
                            </TableCell>
                            <TableCell className="px-4">
                                {metode.Aktif ? (
                                    <LabelStatus jenis="sukses" teks="Aktif" />
                                ) : (
                                    <LabelStatus jenis="netral" teks="Nonaktif" />
                                )}
                            </TableCell>
                            <TableCell className="px-4 text-right">
                                {metode.Wajib ? (
                                    <span className="text-keterangan text-teks-sekunder">Selalu tersedia</span>
                                ) : metode.Aktif ? (
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={memproses !== null}
                                                aria-busy={memproses === metode.Uuid || undefined}
                                            >
                                                {memproses === metode.Uuid
                                                    ? 'Memproses…'
                                                    : `Nonaktifkan ${metode.Nama}`}
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Nonaktifkan {metode.Nama}?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Tombol {metode.Nama} hilang dari layar bayar aplikasi kasir.
                                                    Transaksi lama tidak berubah, dan metode ini bisa diaktifkan lagi.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    variant="destructive"
                                                    onClick={() => UbahStatus(metode)}
                                                >
                                                    Nonaktifkan {metode.Nama}
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                ) : (
                                    <Tombol
                                        varian="sekunder"
                                        onClick={() => UbahStatus(metode)}
                                        memproses={memproses === metode.Uuid}
                                        disabled={memproses !== null}
                                    >
                                        {`Aktifkan ${metode.Nama}`}
                                    </Tombol>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </Card>
    );
}

type PropsFormTambahMetode = {
    jenisTersedia: PropsMetodePembayaranPanduan['JenisTersedia'];
    bank: PropsMetodePembayaranPanduan['Bank'];
    batasGambarQris: PropsMetodePembayaranPanduan['BatasGambarQris'];
};

function FormTambahMetode({ jenisTersedia, bank, batasGambarQris }: PropsFormTambahMetode) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const formulir = useForm<IsianMetodePembayaran>({
        Jenis: jenisTersedia[0]?.Nilai ?? 'QrisStatis',
        Nama: '',
        KodeBank: '',
        GambarQris: null,
        NomorRekening: '',
        NamaPemilikRekening: '',
        PersenBiaya: '',
    });
    const { Jenis } = formulir.data;
    const opsiBank = bank
        .filter((baris) => jenisBankPerMetode[Jenis]?.includes(baris.Jenis))
        .map((baris) => ({ Nilai: baris.Kode, Label: baris.Nama }));

    const GantiJenis = (jenis: string) =>
        formulir.setData({ ...formulir.data, Jenis: jenis, KodeBank: '', GambarQris: null });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        // Isian yang tidak relevan dengan jenis dikirim kosong agar tidak tersimpan.
        formulir.transform((data) => ({
            ...data,
            KodeBank: data.Jenis === 'Edc' || data.Jenis === 'Transfer' ? data.KodeBank || null : null,
            GambarQris: data.Jenis === 'QrisStatis' ? data.GambarQris : null,
            NomorRekening: data.Jenis === 'Transfer' ? data.NomorRekening || null : null,
            NamaPemilikRekening: data.Jenis === 'Transfer' ? data.NamaPemilikRekening || null : null,
            PersenBiaya: data.PersenBiaya || null,
        }));
        formulir.post(AlamatPanduan.MetodePembayaran, {
            preserveScroll: true,
            onSuccess: () => formulir.reset(),
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    return (
        <Card aria-labelledby="judul-tambah-metode" className="gap-4 p-4 sm:p-6" role="region">
            <CardHeader className="px-0">
                <h2 id="judul-tambah-metode" className="text-subjudul font-semibold text-teks-utama">
                    Tambah metode pembayaran
                </h2>
            </CardHeader>
            <CardContent className="px-0">
                <form ref={elemenFormulir} onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                    <RingkasanGalatFormulir galat={formulir.errors} />
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <BidangPilihan
                            label="Jenis"
                            nilai={Jenis}
                            opsi={jenisTersedia}
                            saatBerubah={GantiJenis}
                            galat={formulir.errors.Jenis}
                        />
                        <BidangTeks
                            label="Nama di kasir"
                            nilai={formulir.data.Nama}
                            saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                            galat={formulir.errors.Nama}
                            keterangan={contohNama[Jenis] ?? 'Tampil sebagai tombol di kasir.'}
                            maxLength={60}
                            required
                        />
                        {Jenis === 'Edc' || Jenis === 'Transfer' ? (
                            <BidangPilihan
                                label={Jenis === 'Edc' ? 'Bank penerbit mesin EDC' : 'Bank atau e-wallet tujuan'}
                                nilai={formulir.data.KodeBank}
                                opsi={opsiBank}
                                saatBerubah={(nilai) => formulir.setData('KodeBank', nilai)}
                                galat={formulir.errors.KodeBank}
                                kosong={opsiBank.length === 0 ? 'Data bank belum tersedia' : 'Pilih bank'}
                            />
                        ) : null}
                        {Jenis === 'Transfer' ? (
                            <>
                                <BidangTeks
                                    label="Nomor rekening"
                                    nilai={formulir.data.NomorRekening}
                                    saatBerubah={(nilai) => formulir.setData('NomorRekening', nilai.replace(/\D/g, ''))}
                                    galat={formulir.errors.NomorRekening}
                                    keterangan="Angka saja, 5 sampai 30 digit."
                                    inputMode="numeric"
                                    maxLength={30}
                                    kode
                                    required
                                />
                                <BidangTeks
                                    label="Nama pemilik rekening"
                                    nilai={formulir.data.NamaPemilikRekening}
                                    saatBerubah={(nilai) => formulir.setData('NamaPemilikRekening', nilai)}
                                    galat={formulir.errors.NamaPemilikRekening}
                                    maxLength={100}
                                    required
                                />
                            </>
                        ) : null}
                        <BidangTeks
                            label="Biaya per transaksi (persen, opsional)"
                            nilai={FormatMasukanPersen(formulir.data.PersenBiaya)}
                            saatBerubah={(nilai) => formulir.setData('PersenBiaya', NormalisasiMasukanPersen(nilai))}
                            galat={formulir.errors.PersenBiaya}
                            keterangan="Potongan dari penyedia (MDR), 0 sampai 10 persen, misal 0,7. Dipakai untuk laporan biaya."
                            inputMode="decimal"
                            maxLength={7}
                        />
                    </div>
                    {Jenis === 'QrisStatis' ? (
                        <BidangGambar
                            label="Gambar QRIS"
                            berkas={formulir.data.GambarQris}
                            saatBerubah={(berkas) => formulir.setData('GambarQris', berkas)}
                            ukuranMaksimalKb={batasGambarQris.UkuranMaksimalKb}
                            ekstensi={batasGambarQris.Ekstensi}
                            keterangan="Foto atau unduh QRIS dari aplikasi bank/penyedia Anda. Pastikan kode QR terlihat utuh."
                            galat={formulir.errors.GambarQris}
                        />
                    ) : null}
                    <div>
                        <Tombol type="submit" memproses={formulir.processing}>
                            Tambah metode pembayaran
                        </Tombol>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

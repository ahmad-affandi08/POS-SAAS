import { router, useForm } from '@inertiajs/react';
import { useRef, useState, type FormEvent } from 'react';

import BidangGambar from '@/Komponen/Formulir/BidangGambar';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
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
import { Card, CardContent, CardHeader } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
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
    QrisDinamis: 'Misal "QRIS Otomatis". Tampil sebagai tombol di kasir.',
    Edc: 'Misal "EDC BCA". Tampil sebagai tombol di kasir.',
    Transfer: 'Misal "Transfer BCA". Tampil sebagai tombol di kasir.',
};

/** Langkah 5 F-01: Tunai selalu ada; tambah QRIS statis, QRIS dinamis (F-08), EDC per bank, atau transfer. */
export default function HalamanMetodePembayaran({
    Progres,
    MetodePembayaran,
    JenisTersedia,
    Bank,
    BatasGambarQris,
    GerbangPembayaran,
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

            <FormTambahMetode
                jenisTersedia={JenisTersedia}
                bank={Bank}
                batasGambarQris={BatasGambarQris}
                gerbangPembayaran={GerbangPembayaran}
            />
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

    const kolom: KolomTabel<MetodePembayaranRingkas>[] = [
        {
            id: 'Nama',
            accessorKey: 'Nama',
            header: 'Nama',
            meta: { label: 'Nama', prioritas: 'utama', wajib: true },
            cell: ({ row: { original: metode } }) => (
                <>
                    <span className="break-words text-teks-utama">{metode.Nama}</span>
                    <span className="block text-keterangan text-teks-sekunder">{metode.LabelJenis}</span>
                </>
            ),
        },
        {
            id: 'Rincian',
            header: 'Rincian',
            enableSorting: false,
            meta: { label: 'Rincian', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
            cell: ({ row: { original: metode } }) => (
                <>
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
                </>
            ),
        },
        {
            id: 'PersenBiaya',
            header: 'Biaya',
            enableSorting: false,
            meta: { label: 'Biaya', angka: true, prioritas: 'penting' },
            cell: ({ row }) => `${FormatPersen(row.original.PersenBiaya)}%`,
        },
        {
            id: 'Aktif',
            accessorKey: 'Aktif',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row }) =>
                row.original.Aktif ? (
                    <LabelStatus jenis="sukses" teks="Aktif" />
                ) : (
                    <LabelStatus jenis="netral" teks="Nonaktif" />
                ),
        },
        {
            id: 'Tindakan',
            header: () => <span className="sr-only">Tindakan</span>,
            enableSorting: false,
            meta: { label: 'Tindakan', prioritas: 'penting', wajib: true, kelasSel: 'text-right' },
            cell: ({ row: { original: metode } }) =>
                metode.Wajib ? (
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
                                {memproses === metode.Uuid ? 'Memproses…' : `Nonaktifkan ${metode.Nama}`}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Nonaktifkan {metode.Nama}?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Tombol {metode.Nama} hilang dari layar bayar aplikasi kasir. Transaksi lama tidak
                                    berubah, dan metode ini bisa diaktifkan lagi.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction variant="destructive" onClick={() => UbahStatus(metode)}>
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
                ),
        },
    ];

    return (
        <TabelData
            id="panduan-metode-pembayaran"
            label="Metode pembayaran"
            kolom={kolom}
            sumber={{ mode: 'lokal', data: metodePembayaran }}
            ambilIdBaris={(metode) => metode.Uuid}
            kosong={{ judul: 'Belum ada metode pembayaran yang tercatat. Tunai selalu tersedia di kasir.' }}
        />
    );
}

type PropsFormTambahMetode = {
    jenisTersedia: PropsMetodePembayaranPanduan['JenisTersedia'];
    bank: PropsMetodePembayaranPanduan['Bank'];
    batasGambarQris: PropsMetodePembayaranPanduan['BatasGambarQris'];
    gerbangPembayaran: PropsMetodePembayaranPanduan['GerbangPembayaran'];
};

function FormTambahMetode({ jenisTersedia, bank, batasGambarQris, gerbangPembayaran }: PropsFormTambahMetode) {
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
                            required
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
                                required
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
                    {Jenis === 'QrisDinamis' ? <InfoQrisDinamis gerbangPembayaran={gerbangPembayaran} /> : null}
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

/** F-08: QRIS dinamis dibuat per transaksi lewat gerbang pembayaran yang diaktifkan pengelola platform. */
function InfoQrisDinamis({
    gerbangPembayaran,
}: {
    gerbangPembayaran: PropsMetodePembayaranPanduan['GerbangPembayaran'];
}) {
    return gerbangPembayaran.Aktif ? (
        <Pemberitahuan jenis="info" judul={`Gerbang pembayaran aktif: ${gerbangPembayaran.Penyedia ?? 'tersedia'}`}>
            Kasir membuat kode QR berisi jumlah tagihan untuk setiap transaksi. Status lunas masuk otomatis, jadi kasir
            tidak perlu memeriksa mutasi rekening. Aplikasi kasir harus tersambung internet saat memakai metode ini.
        </Pemberitahuan>
    ) : (
        <Pemberitahuan jenis="peringatan" judul="Gerbang pembayaran belum aktif">
            QRIS dinamis butuh akun merchant toko di salah satu penyedia gerbang pembayaran (dana langsung ke rekening
            toko). Metode ini tetap bisa ditambahkan, tetapi kasir baru bisa memakainya setelah gerbang diaktifkan di{' '}
            <a
                href={gerbangPembayaran.Tautan ?? '/kelola/pembayaran/gerbang'}
                className="font-semibold text-brand underline underline-offset-2"
            >
                Gerbang pembayaran
            </a>
            .
        </Pemberitahuan>
    );
}

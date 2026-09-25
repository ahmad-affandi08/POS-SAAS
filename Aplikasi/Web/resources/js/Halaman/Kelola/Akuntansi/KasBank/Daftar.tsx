import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangBerkas from '@/Komponen/Formulir/BidangBerkas';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Badge } from '@/Komponen/Ui/badge';
import { Button } from '@/Komponen/Ui/button';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { TulisTanggal } from '@/Pustaka/Tanggal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type {
    BarisSaldoKasBank,
    BarisTransaksiKasBank,
    JenisTransaksiKasBank,
    OpsiAkunKasBank,
    PropsDaftarTransaksiKasBank,
    TipeAkun,
} from '@/Tipe/Akuntansi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

const alamat = '/kelola/akuntansi/kas-bank';

/** Aturan akun per jenis (sama dengan server): sisi kas/bank & tipe akun lawan yang boleh. */
const aturanJenis: Record<
    JenisTransaksiKasBank,
    {
        labelSumber: string;
        labelTujuan: string;
        sumberKas: boolean;
        tujuanKas: boolean;
        lawan: TipeAkun[];
        penjelasan: string;
    }
> = {
    Pengeluaran: {
        labelSumber: 'Dibayar dari (kas/bank)',
        labelTujuan: 'Untuk akun beban/aset',
        sumberKas: true,
        tujuanKas: false,
        lawan: ['Beban', 'Aset'],
        penjelasan: 'Biaya operasional seperti sewa, listrik, gaji, atau pembelian perlengkapan.',
    },
    Penerimaan: {
        labelSumber: 'Diterima dari akun',
        labelTujuan: 'Masuk ke (kas/bank)',
        sumberKas: false,
        tujuanKas: true,
        lawan: ['Pendapatan', 'Ekuitas', 'Kewajiban', 'Aset'],
        penjelasan: 'Uang masuk di luar penjualan: setoran modal, pinjaman, bunga bank, pendapatan lain.',
    },
    Transfer: {
        labelSumber: 'Dari kas/bank',
        labelTujuan: 'Ke kas/bank',
        sumberKas: true,
        tujuanKas: true,
        lawan: [],
        penjelasan: 'Pindah dana antar akun kas/bank, misalnya setoran kas brankas ke bank.',
    },
};

function OpsiAkun(akun: OpsiAkunKasBank[], kasBank: boolean, lawan: TipeAkun[]) {
    return akun
        .filter((a) => (kasBank ? a.KasBank : !a.KasBank && lawan.includes(a.Jenis)))
        .map((a) => ({ Nilai: a.Uuid, Label: `${a.Kode} ${a.Nama}` }));
}

const kolomSaldo: KolomTabel<BarisSaldoKasBank>[] = [
    {
        id: 'Akun',
        accessorFn: (a) => `${a.Kode} ${a.Nama}`,
        header: 'Akun kas/bank',
        meta: { label: 'Akun kas/bank', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-wrap items-center gap-1.5">
                <span className="font-mono">{a.Kode}</span>
                <span className="break-words">{a.Nama}</span>
                {a.Aktif ? null : <Badge variant="outline">Nonaktif</Badge>}
            </span>
        ),
    },
    {
        id: 'Saldo',
        accessorKey: 'Saldo',
        header: 'Saldo',
        enableSorting: false,
        meta: { label: 'Saldo', angka: true, prioritas: 'utama' },
        cell: ({ row }) => FormatRupiah(row.original.Saldo),
    },
];

const kolom: KolomTabel<BarisTransaksiKasBank>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor',
        meta: { label: 'Nomor', prioritas: 'utama', wajib: true, kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => (
            <Link href={`${alamat}/${row.original.Uuid}`} className="font-mono font-semibold text-brand underline">
                {row.original.Nomor}
            </Link>
        ),
    },
    {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: 'Tanggal',
        meta: { label: 'Tanggal', prioritas: 'penting', kelasSel: 'whitespace-nowrap text-teks-sekunder' },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    },
    {
        id: 'Jenis',
        header: 'Jenis',
        enableSorting: false,
        meta: { label: 'Jenis', prioritas: 'penting' },
        cell: ({ row: { original: t } }) => (
            <span className="flex flex-wrap items-center gap-1.5">
                {t.LabelJenis}
                {t.Pembalik ? <Badge variant="outline">Pembalik</Badge> : null}
                {t.Dibalik ? <Badge variant="outline">Sudah dibalik</Badge> : null}
            </span>
        ),
    },
    {
        id: 'Akun',
        header: 'Akun',
        enableSorting: false,
        meta: { label: 'Akun', prioritas: 'rendah', kelasSel: 'text-label' },
        cell: ({ row: { original: t } }) => (
            <span className="break-words">
                {t.AkunSumber} → {t.AkunTujuan}
            </span>
        ),
    },
    {
        id: 'Keterangan',
        header: 'Keterangan',
        enableSorting: false,
        meta: { label: 'Keterangan', prioritas: 'rendah' },
        cell: ({ row }) => <span className="break-words">{row.original.Keterangan}</span>,
    },
    {
        id: 'Outlet',
        header: 'Outlet',
        enableSorting: false,
        meta: { label: 'Outlet', prioritas: 'rendah' },
        cell: ({ row }) => row.original.NamaOutlet ?? 'Tingkat usaha',
    },
    {
        id: 'Jumlah',
        accessorKey: 'Jumlah',
        header: 'Jumlah',
        meta: { label: 'Jumlah', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.Jumlah),
    },
];

type IsianTransaksi = {
    Jenis: JenisTransaksiKasBank;
    Tanggal: string;
    UuidOutlet: string;
    UuidAkunSumber: string;
    UuidAkunTujuan: string;
    Jumlah: string;
    Keterangan: string;
    Lampiran: File[];
};

/** F-13a transaksi kas & bank (FIN-03): saldo per akun kas/bank, daftar transaksi, dan catat transaksi baru. */
export default function HalamanTransaksiKasBank({
    Transaksi,
    Saldo,
    OpsiJenis,
    OpsiOutlet,
    OpsiAkun: akun,
    WajibOutlet,
    Lampiran,
    Izin,
}: PropsDaftarTransaksiKasBank) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<IsianTransaksi | null>(null);
    const [memproses, AturMemproses] = useState(false);
    const aturan = isian ? aturanJenis[isian.Jenis] : null;
    const saring: DefinisiSaring[] = [
        { id: 'Tanggal', label: 'Tanggal', jenis: 'rentangTanggal' },
        {
            id: 'Jenis',
            label: 'Jenis',
            jenis: 'pilihanBanyak',
            opsi: OpsiJenis.map((j) => ({ nilai: j.Nilai, label: j.Label })),
        },
        ...(OpsiOutlet.length > 1
            ? [
                  {
                      id: 'Outlet',
                      label: 'Outlet',
                      jenis: 'pilihanBanyak' as const,
                      opsi: OpsiOutlet.map((o) => ({ nilai: o.Uuid, label: o.Nama })),
                  },
              ]
            : []),
    ];

    const Buka = () =>
        AturIsian({
            Jenis: 'Pengeluaran',
            Tanggal: TulisTanggal(new Date()),
            UuidOutlet: WajibOutlet && OpsiOutlet.length === 1 ? (OpsiOutlet[0]?.Uuid ?? '') : '',
            UuidAkunSumber: '',
            UuidAkunTujuan: '',
            Jumlah: '',
            Keterangan: '',
            Lampiran: [],
        });

    const Ubah = (ubah: Partial<IsianTransaksi>) => {
        if (isian !== null) {
            AturIsian({ ...isian, ...ubah });
        }
    };

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        if (isian === null) {
            return;
        }

        const { Lampiran: berkas, ...data } = isian;
        router.post(
            alamat,
            { ...data, UuidOutlet: data.UuidOutlet === '' ? null : data.UuidOutlet, Lampiran: berkas[0] ?? null },
            {
                forceFormData: berkas.length > 0,
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onSuccess: () => AturIsian(null),
            },
        );
    };

    return (
        <TataLetakAplikasi judul="Kas & bank">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Catat pengeluaran operasional, penerimaan di luar penjualan, dan transfer antar kas/bank. Setiap
                transaksi langsung dijurnal. Transaksi yang sudah disimpan tidak bisa diubah; koreksi dengan dokumen
                pembalik.
            </p>

            <TabelData
                id="akuntansi-saldo-kas-bank"
                label="Saldo kas & bank"
                kolom={kolomSaldo}
                sumber={{ mode: 'lokal', data: Saldo }}
                ambilIdBaris={(a) => a.Uuid}
                cari={false}
                kosong={{ judul: 'Belum ada akun kas/bank. Tandai akun kas atau bank di Bagan akun.' }}
            />

            {Izin.Kelola ? (
                <div>
                    <Button onClick={Buka}>Catat transaksi kas & bank</Button>
                </div>
            ) : (
                <PesanHanyaLihat izin="akuntansi.kelola" objek="transaksi kas & bank" />
            )}

            <TabelData
                id="akuntansi-kas-bank"
                label="Daftar transaksi kas & bank"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Transaksi }}
                ambilIdBaris={(t) => t.Uuid}
                urutBawaan="-Tanggal"
                cari="Cari nomor atau keterangan"
                saring={saring}
                alamatDetail={(t) => `${alamat}/${t.Uuid}`}
                kosong={{ judul: 'Belum ada transaksi kas & bank.' }}
            />

            {isian !== null && aturan !== null ? (
                <DialogFormulir
                    judul="Catat transaksi kas & bank"
                    jenis="panel"
                    galatUmum={galat.Umum}
                    saatTutup={() => AturIsian(null)}
                >
                    <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir transaksi kas & bank">
                        <BidangPilihan
                            label="Jenis transaksi"
                            nilai={isian.Jenis}
                            opsi={OpsiJenis}
                            saatBerubah={(nilai) =>
                                Ubah({ Jenis: nilai as JenisTransaksiKasBank, UuidAkunSumber: '', UuidAkunTujuan: '' })
                            }
                            galat={galat.Jenis}
                        />
                        <p className="text-label text-teks-sekunder">{aturan.penjelasan}</p>
                        <PemilihTanggal
                            label="Tanggal"
                            nilai={isian.Tanggal}
                            saatBerubah={(nilai) => Ubah({ Tanggal: nilai })}
                            galat={galat.Tanggal}
                            required
                        />
                        <BidangPilihan
                            label="Outlet"
                            nilai={isian.UuidOutlet}
                            opsi={OpsiOutlet.map((o) => ({ Nilai: o.Uuid, Label: o.Nama }))}
                            {...(WajibOutlet ? { kosong: 'Pilih outlet' } : { kosong: 'Tingkat usaha (tanpa outlet)' })}
                            saatBerubah={(nilai) => Ubah({ UuidOutlet: nilai })}
                            galat={galat.UuidOutlet}
                        />
                        <BidangPilihan
                            label={aturan.labelSumber}
                            nilai={isian.UuidAkunSumber}
                            kosong="Pilih akun"
                            opsi={OpsiAkun(akun, aturan.sumberKas, aturan.lawan)}
                            saatBerubah={(nilai) => Ubah({ UuidAkunSumber: nilai })}
                            galat={galat.UuidAkunSumber}
                        />
                        <BidangPilihan
                            label={aturan.labelTujuan}
                            nilai={isian.UuidAkunTujuan}
                            kosong="Pilih akun"
                            opsi={OpsiAkun(akun, aturan.tujuanKas, aturan.lawan)}
                            saatBerubah={(nilai) => Ubah({ UuidAkunTujuan: nilai })}
                            galat={galat.UuidAkunTujuan}
                        />
                        <BidangUang
                            label="Jumlah"
                            nilai={isian.Jumlah}
                            saatBerubah={(nilai) => Ubah({ Jumlah: nilai })}
                            galat={galat.Jumlah}
                            required
                        />
                        <BidangTeksPanjang
                            label="Keterangan"
                            nilai={isian.Keterangan}
                            saatBerubah={(nilai) => Ubah({ Keterangan: nilai })}
                            galat={galat.Keterangan}
                            maksimal={255}
                            required
                        />
                        <BidangBerkas
                            label="Lampiran (nota atau bukti transfer)"
                            berkas={isian.Lampiran}
                            saatBerubah={(berkas) => Ubah({ Lampiran: berkas })}
                            ekstensi={Lampiran.Ekstensi}
                            maksimal={1}
                            ukuranMaksimalKb={Lampiran.UkuranMaksimalKb}
                            galat={galat.Lampiran}
                        />
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => AturIsian(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={memproses}>
                                Simpan & jurnal
                            </Button>
                        </div>
                    </form>
                </DialogFormulir>
            ) : null}
        </TataLetakAplikasi>
    );
}

import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangBerkas from '@/Komponen/Formulir/BidangBerkas';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import KartuFormulir from '@/Komponen/Formulir/KartuFormulir';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { Button } from '@/Komponen/Ui/button';
import { TulisTanggal } from '@/Pustaka/Tanggal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { JenisTransaksiKasBank, OpsiAkunKasBank, PropsBuatTransaksiKasBank, TipeAkun } from '@/Tipe/Akuntansi';
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

/**
 * F-13a halaman "Catat transaksi kas & bank" (FIN-03). Setelah disimpan (langsung dijurnal), server mengarahkan ke
 * detail dokumen baru.
 */
export default function HalamanBuatTransaksiKasBank({
    OpsiJenis,
    OpsiOutlet,
    OpsiAkun: akun,
    WajibOutlet,
    Lampiran,
}: PropsBuatTransaksiKasBank) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<IsianTransaksi>(() => ({
        Jenis: 'Pengeluaran',
        Tanggal: TulisTanggal(new Date()),
        UuidOutlet: WajibOutlet && OpsiOutlet.length === 1 ? (OpsiOutlet[0]?.Uuid ?? '') : '',
        UuidAkunSumber: '',
        UuidAkunTujuan: '',
        Jumlah: '',
        Keterangan: '',
        Lampiran: [],
    }));
    const [memproses, AturMemproses] = useState(false);
    const aturan = aturanJenis[isian.Jenis];

    const Ubah = (ubah: Partial<IsianTransaksi>) => AturIsian({ ...isian, ...ubah });

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        const { Lampiran: berkas, ...data } = isian;
        router.post(
            alamat,
            { ...data, UuidOutlet: data.UuidOutlet === '' ? null : data.UuidOutlet, Lampiran: berkas[0] ?? null },
            {
                forceFormData: berkas.length > 0,
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
            },
        );
    };

    return (
        <TataLetakAplikasi judul="Catat transaksi kas & bank">
            <DaftarGalatServer galat={galat} kecuali={Object.keys(isian)} />
            <KartuFormulir keterangan="Pengeluaran operasional, penerimaan di luar penjualan, atau transfer antar kas/bank. Transaksi langsung dijurnal saat disimpan dan tidak bisa diubah; koreksi dengan dokumen pembalik.">
                <form
                    onSubmit={Simpan}
                    className="flex flex-col gap-4"
                    aria-label="Formulir transaksi kas & bank"
                    noValidate
                >
                    <BidangPilihan
                        label="Jenis transaksi"
                        nilai={isian.Jenis}
                        opsi={OpsiJenis}
                        saatBerubah={(nilai) =>
                            Ubah({ Jenis: nilai as JenisTransaksiKasBank, UuidAkunSumber: '', UuidAkunTujuan: '' })
                        }
                        galat={galat.Jenis}
                        required
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
                        required={WajibOutlet}
                    />
                    <BidangPilihan
                        label={aturan.labelSumber}
                        nilai={isian.UuidAkunSumber}
                        kosong="Pilih akun"
                        opsi={OpsiAkun(akun, aturan.sumberKas, aturan.lawan)}
                        saatBerubah={(nilai) => Ubah({ UuidAkunSumber: nilai })}
                        galat={galat.UuidAkunSumber}
                        required
                    />
                    <BidangPilihan
                        label={aturan.labelTujuan}
                        nilai={isian.UuidAkunTujuan}
                        kosong="Pilih akun"
                        opsi={OpsiAkun(akun, aturan.tujuanKas, aturan.lawan)}
                        saatBerubah={(nilai) => Ubah({ UuidAkunTujuan: nilai })}
                        galat={galat.UuidAkunTujuan}
                        required
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
                        <Button type="button" variant="outline" onClick={() => router.visit(alamat)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={memproses}>
                            Simpan & jurnal
                        </Button>
                    </div>
                </form>
            </KartuFormulir>
        </TataLetakAplikasi>
    );
}

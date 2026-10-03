import { Link } from '@inertiajs/react';

import { KolomBilangan, KolomUang } from '@/Komponen/Laporan/KolomLaporan';
import SaringLaporan from '@/Komponen/Laporan/SaringLaporan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Card } from '@/Komponen/Ui/card';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';

type BarisMrrPaket = { Kunci: string; Nama: string; Pelanggan: number; Mrr: string };
type BarisPendapatan = { Kunci: string; Nama: string; JumlahTagihan: number; Pendapatan: string };
type BarisUmur = { Kunci: string; Label: string; Jumlah: number; Total: string };

export type PropsLaporanLangganan = {
    Saring: { Dari: string; Sampai: string };
    HariMasaTenggang: number;
    Ringkasan: { Pada: string; Mrr: string; Arr: string; PelangganBerbayar: number; RataRataPerPelanggan: string };
    Churn: {
        PelangganAwal: number;
        PelangganAkhir: number;
        PelangganBaru: number;
        PelangganBerhenti: number;
        PersenChurn: string;
        MrrAwal: string;
        MrrBaru: string;
        MrrBerhenti: string;
        PersenChurnMrr: string;
    };
    MrrPerPaket: BarisMrrPaket[];
    PendapatanPerPaket: BarisPendapatan[];
    PendapatanPerSektor: BarisPendapatan[];
    Piutang: { Total: string; Jumlah: number; Umur: BarisUmur[] };
};

const kolomMrr: KolomTabel<BarisMrrPaket>[] = [
    { id: 'Nama', accessorKey: 'Nama', header: 'Paket', meta: { label: 'Paket', prioritas: 'utama', wajib: true } },
    KolomBilangan<BarisMrrPaket>('Pelanggan', 'Pelanggan', 'penting'),
    KolomUang<BarisMrrPaket>('Mrr', 'MRR'),
];

function KolomPendapatan(label: string): KolomTabel<BarisPendapatan>[] {
    return [
        { id: 'Nama', accessorKey: 'Nama', header: label, meta: { label, prioritas: 'utama', wajib: true } },
        KolomBilangan<BarisPendapatan>('JumlahTagihan', 'Tagihan lunas', 'penting'),
        KolomUang<BarisPendapatan>('Pendapatan', 'Pendapatan'),
    ];
}

const kolomPaket = KolomPendapatan('Paket');
const kolomSektor = KolomPendapatan('Sektor');

const kolomUmur: KolomTabel<BarisUmur>[] = [
    { id: 'Label', accessorKey: 'Label', header: 'Umur', meta: { label: 'Umur', prioritas: 'utama', wajib: true } },
    KolomBilangan<BarisUmur>('Jumlah', 'Tagihan', 'penting'),
    KolomUang<BarisUmur>('Total', 'Total'),
];

function Angka({ label, nilai, keterangan }: { label: string; nilai: string; keterangan?: string }) {
    return (
        <Card className="flex flex-col gap-1 p-4 rounded-panel shadow-none">
            <span className="text-keterangan text-teks-sekunder">{label}</span>
            <span className="text-subjudul font-semibold text-teks-utama tabular-nums">{nilai}</span>
            {keterangan ? <span className="text-keterangan text-teks-sekunder">{keterangan}</span> : null}
        </Card>
    );
}

/**
 * P-08 langkah 7 (PRD v4.09): laporan langganan platform — MRR/ARR, churn periode, pendapatan per paket & sektor, dan
 * umur piutang langganan. Angka dari tagihan langganan (tanpa PPN), tenant uji/demo/internal tidak dihitung.
 */
export default function HalamanLaporanLangganan({
    Saring,
    HariMasaTenggang,
    Ringkasan,
    Churn,
    MrrPerPaket,
    PendapatanPerPaket,
    PendapatanPerSektor,
    Piutang,
}: PropsLaporanLangganan) {
    const query = { dari: Saring.Dari, sampai: Saring.Sampai };

    return (
        <TataLetakPengelola judul="Laporan langganan" jejak={[{ label: 'Tagihan', href: '/tagihan' }]}>
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Dari tagihan langganan yang lunas, tanpa PPN. Pelanggan berbayar = punya tagihan lunas yang periodenya
                masih berjalan (termasuk masa tenggang {String(HariMasaTenggang)} hari). Tenant uji, demo, dan internal
                tidak dihitung.
            </p>

            <SaringLaporan alamat="/laporan-langganan" query={query} maksHari={366} pilihan={[]} />

            <section aria-labelledby="judul-ringkasan" className="flex flex-col gap-2">
                <h2 id="judul-ringkasan" className="text-subjudul font-semibold text-teks-utama">
                    Pendapatan berulang
                </h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Angka
                        label="MRR"
                        nilai={FormatRupiah(Ringkasan.Mrr)}
                        keterangan={`Per ${FormatTanggalWaktu(Ringkasan.Pada)}`}
                    />
                    <Angka label="ARR" nilai={FormatRupiah(Ringkasan.Arr)} keterangan="MRR × 12" />
                    <Angka label="Pelanggan berbayar" nilai={String(Ringkasan.PelangganBerbayar)} />
                    <Angka
                        label="Rata-rata per pelanggan"
                        nilai={FormatRupiah(Ringkasan.RataRataPerPelanggan)}
                        keterangan="per bulan"
                    />
                </div>
            </section>

            <section aria-labelledby="judul-churn" className="flex flex-col gap-2">
                <h2 id="judul-churn" className="text-subjudul font-semibold text-teks-utama">
                    Churn periode ini
                </h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Angka
                        label="Churn pelanggan"
                        nilai={`${Churn.PersenChurn}%`}
                        keterangan={`${String(Churn.PelangganBerhenti)} dari ${String(Churn.PelangganAwal)} pelanggan awal berhenti`}
                    />
                    <Angka
                        label="Churn MRR"
                        nilai={`${Churn.PersenChurnMrr}%`}
                        keterangan={`${FormatRupiah(Churn.MrrBerhenti)} dari ${FormatRupiah(Churn.MrrAwal)}`}
                    />
                    <Angka
                        label="Pelanggan baru"
                        nilai={String(Churn.PelangganBaru)}
                        keterangan={`MRR baru ${FormatRupiah(Churn.MrrBaru)}`}
                    />
                    <Angka label="Pelanggan akhir periode" nilai={String(Churn.PelangganAkhir)} />
                </div>
            </section>

            <section aria-labelledby="judul-mrr-paket" className="flex flex-col gap-2">
                <h2 id="judul-mrr-paket" className="text-subjudul font-semibold text-teks-utama">
                    MRR per paket
                </h2>
                <TabelData
                    id="laporan-langganan-mrr-paket"
                    label="MRR per paket"
                    kolom={kolomMrr}
                    sumber={{ mode: 'lokal', data: MrrPerPaket }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="-Mrr"
                    cari={false}
                    kosong={{ ilustrasi: true, judul: 'Belum ada pelanggan berbayar.' }}
                />
            </section>

            <section aria-labelledby="judul-pendapatan" className="flex flex-col gap-2">
                <h2 id="judul-pendapatan" className="text-subjudul font-semibold text-teks-utama">
                    Pendapatan langganan dibayar pada periode ini
                </h2>
                <div className="grid gap-4 lg:grid-cols-2">
                    <TabelData
                        id="laporan-langganan-pendapatan-paket"
                        label="Pendapatan per paket"
                        kolom={kolomPaket}
                        sumber={{ mode: 'lokal', data: PendapatanPerPaket }}
                        ambilIdBaris={(b) => b.Kunci}
                        urutBawaan="-Pendapatan"
                        cari={false}
                        kosong={{ ilustrasi: true, judul: 'Tidak ada tagihan lunas pada periode ini.' }}
                    />
                    <TabelData
                        id="laporan-langganan-pendapatan-sektor"
                        label="Pendapatan per sektor"
                        kolom={kolomSektor}
                        sumber={{ mode: 'lokal', data: PendapatanPerSektor }}
                        ambilIdBaris={(b) => b.Kunci}
                        urutBawaan="-Pendapatan"
                        cari={false}
                        kosong={{ ilustrasi: true, judul: 'Tidak ada tagihan lunas pada periode ini.' }}
                    />
                </div>
            </section>

            <section aria-labelledby="judul-piutang" className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="judul-piutang" className="text-subjudul font-semibold text-teks-utama">
                        Piutang langganan hari ini: {FormatRupiah(Piutang.Total)} ({String(Piutang.Jumlah)} tagihan)
                    </h2>
                    <Link href="/tagihan" className="text-label text-brand underline">
                        Lihat tagihan
                    </Link>
                </div>
                <TabelData
                    id="laporan-langganan-piutang"
                    label="Umur piutang langganan"
                    kolom={kolomUmur}
                    sumber={{ mode: 'lokal', data: Piutang.Umur }}
                    ambilIdBaris={(b) => b.Kunci}
                    cari={false}
                    kosong={{ ilustrasi: true, judul: 'Tidak ada tagihan terbuka.' }}
                />
            </section>
        </TataLetakPengelola>
    );
}

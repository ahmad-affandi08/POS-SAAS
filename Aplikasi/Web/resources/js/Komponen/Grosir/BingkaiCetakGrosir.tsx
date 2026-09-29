import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Button } from '@/Komponen/Ui/button';
import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import { FormatRupiah } from '@/Pustaka/Format';
import type { OutletCetak, PelangganCetak, UsahaCetak } from '@/Tipe/Grosir';

/*
 * Bingkai bersama dokumen grosir yang dicetak (F-12, §9.7): kepala surat, alamat penjual & pembeli, tanda batal, dan
 * kolom tanda tangan. Tanpa kerangka aplikasi (`TataLetakAplikasi`) supaya yang keluar di kertas cuma dokumennya —
 * mengikuti pola `Kelola/Pembelian/Pesanan/Cetak`.
 *
 * Halaman cetak adalah pengecualian `TabelData` (§25.2 no. 17): rinciannya rincian dokumen, bukan tabel kerja yang
 * dicari & disaring.
 */

/** Satu baris keterangan di kepala kanan dokumen ("Tanggal 27 Sep 2026"). */
export function BarisKepalaCetak({ label, children }: { label: string; children: ReactNode }) {
    return (
        <p>
            {label} <span className="font-semibold">{children}</span>
        </p>
    );
}

/** Blok alamat (penjual, pembeli, atau tujuan kirim). */
export function AlamatCetak({ judul, children }: { judul: string; children: ReactNode }) {
    return (
        <div>
            <h2 className="text-label font-semibold text-teks-sekunder">{judul}</h2>
            {children}
        </div>
    );
}

export function AlamatPembeliCetak({ judul = 'Kepada', pelanggan }: { judul?: string; pelanggan: PelangganCetak }) {
    return (
        <AlamatCetak judul={judul}>
            <p className="font-semibold">{pelanggan.Nama}</p>
            {pelanggan.Alamat ? <p className="whitespace-pre-line">{pelanggan.Alamat}</p> : null}
            {pelanggan.NoHp ? <p>{pelanggan.NoHp}</p> : null}
        </AlamatCetak>
    );
}

export function AlamatPenjualCetak({ judul = 'Dari', outlet }: { judul?: string; outlet: OutletCetak }) {
    return (
        <AlamatCetak judul={judul}>
            <p className="font-semibold">{outlet.Nama}</p>
            <p className="font-mono text-keterangan">{outlet.Kode}</p>
            {outlet.Alamat ? <p className="whitespace-pre-line">{outlet.Alamat}</p> : null}
        </AlamatCetak>
    );
}

/** Ringkasan nilai dokumen: label kiri, Rupiah tabular rata kanan, Total bergaris atas. */
export function RingkasanCetak({
    baris,
    labelTotal,
    total,
}: {
    baris: { label: string; nilai: string }[];
    labelTotal: string;
    total: string;
}) {
    return (
        <dl className="ml-auto flex w-full max-w-sm flex-col gap-1">
            {baris.map((b) => (
                <div key={b.label} className="flex justify-between gap-3">
                    <dt className="text-teks-sekunder">{b.label}</dt>
                    <dd className="tabular-nums">{FormatRupiah(b.nilai)}</dd>
                </div>
            ))}
            <div className="flex justify-between gap-3 border-t border-garis pt-1 font-semibold">
                <dt>{labelTotal}</dt>
                <dd className="tabular-nums">{FormatRupiah(total)}</dd>
            </div>
        </dl>
    );
}

export default function BingkaiCetakGrosir({
    judulDokumen,
    judulTab,
    nomor,
    usaha,
    kepala,
    dibatalkan,
    alasanBatal,
    tandaTangan,
    children,
}: {
    judulDokumen: string;
    judulTab: string;
    nomor: string;
    usaha: UsahaCetak;
    /** Baris keterangan di kepala kanan (tanggal, jatuh tempo, nomor Faktur Pajak). */
    kepala: ReactNode;
    dibatalkan: boolean;
    alasanBatal: string | null;
    /** Kolom tanda tangan di kaki dokumen; nama tercetak bila sudah diketahui. */
    tandaTangan: { label: string; nama?: string | null }[];
    children: ReactNode;
}) {
    return (
        <main className="mx-auto flex max-w-4xl flex-col gap-6 bg-permukaan p-6 text-isi text-teks-utama print:p-0">
            <Head title={judulTab} />
            <div className="flex justify-end print:hidden">
                <Button onClick={() => window.print()}>Cetak atau simpan PDF</Button>
            </div>

            {dibatalkan ? (
                <p className="border-2 border-bahaya p-3 text-center text-subjudul font-bold text-bahaya uppercase">
                    Dibatalkan{alasanBatal ? <span className="block text-isi font-normal">{alasanBatal}</span> : null}
                </p>
            ) : null}

            <header className="flex flex-wrap items-start justify-between gap-4 border-b border-garis pb-4">
                <div>
                    <p className="text-subjudul font-semibold">{usaha.Nama ?? ''}</p>
                    {usaha.Npwp ? <p className="text-teks-sekunder">NPWP {usaha.Npwp}</p> : null}
                </div>
                <div className="text-right">
                    <JudulHalaman>{judulDokumen}</JudulHalaman>
                    <p className="font-mono">{nomor}</p>
                    {kepala}
                </div>
            </header>

            {children}

            <footer
                className={`mt-8 grid gap-8 text-center ${tandaTangan.length >= 3 ? 'grid-cols-3' : 'grid-cols-2'}`}
            >
                {tandaTangan.map((t) => (
                    <div key={t.label}>
                        <p>{t.label}</p>
                        <p className="mt-16 border-t border-garis pt-1">{t.nama ?? ''}</p>
                    </div>
                ))}
            </footer>
        </main>
    );
}

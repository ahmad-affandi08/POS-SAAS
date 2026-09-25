import { Head } from '@inertiajs/react';

import { PENUTUP_BAWAAN } from '@/Komponen/Struk/PratinjauStruk';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';

export type StrukDigital = {
    NamaUsaha: string;
    TeksKepala: string[];
    NamaOutlet: string | null;
    Alamat: string | null;
    Npwp: string | null;
    Nomor: string;
    Waktu: string;
    NamaKasir: string | null;
    NamaPelanggan: string | null;
    Dibatalkan: boolean;
    Baris: {
        NamaProduk: string;
        Pilihan: string[];
        Jumlah: string;
        HargaSatuan: string;
        Diskon: string;
        Total: string;
    }[];
    Subtotal: string;
    TotalDiskon: string;
    BiayaLayanan: string;
    Pajak: { Kode: string; Tarif: string; Jumlah: string }[];
    Pembulatan: string;
    TotalAkhir: string;
    Pembayaran: { NamaMetode: string; Jumlah: string }[];
    Kembalian: string;
    TotalRetur: string | null;
    CatatanKaki: string | null;
    TeksPenutup: string | null;
};

/** Nilai uang nol ("0.00", "-0.00"). */
function CekNol(nilai: string): boolean {
    return /^-?0+(\.0+)?$/.test(nilai);
}

/** Jumlah tanpa nol desimal berlebih ("2.000" → "2"). */
function FormatJumlah(nilai: string): string {
    return nilai.includes('.') ? nilai.replace(/\.?0+$/, '') : nilai;
}

function Baris({ kiri, kanan, tebal = false }: { kiri: string; kanan: string; tebal?: boolean }) {
    return (
        <div className={`flex justify-between gap-4 ${tebal ? 'font-bold text-teks-utama' : ''}`}>
            <span>{kiri}</span>
            <span className="tabular-nums">{kanan}</span>
        </div>
    );
}

/**
 * Struk digital publik (POS-11, `/s/{kodeStruk}`): isi sama dengan struk cetak, font Mono, tanpa login. Struk yang
 * belum terkirim dari kasir, dimatikan, atau tidak dikenal menampilkan keadaan "belum tersedia".
 */
export default function HalamanStrukDigital({ Struk }: { Struk: StrukDigital | null }) {
    if (Struk === null) {
        return (
            <>
                <Head title="Struk belum tersedia" />
                <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-2 px-4 py-10">
                    <h1 className="text-subjudul font-semibold text-teks-utama">Struk belum tersedia</h1>
                    <p className="text-isi text-teks-sekunder">
                        Struk ini belum terkirim dari kasir atau tautannya salah. Coba buka lagi beberapa saat kemudian.
                    </p>
                </main>
            </>
        );
    }

    return (
        <>
            <Head title={`Struk ${Struk.Nomor}`} />
            <main className="mx-auto max-w-md px-4 py-8">
                <article
                    aria-label={`Struk ${Struk.Nomor}`}
                    className="flex flex-col gap-3 rounded-kontrol border border-garis bg-permukaan p-4 font-mono text-keterangan text-teks-sekunder"
                >
                    <header className="text-center">
                        <h1 className="text-isi font-bold text-teks-utama">{Struk.NamaUsaha}</h1>
                        {Struk.NamaOutlet ? <p>{Struk.NamaOutlet}</p> : null}
                        {Struk.Alamat ? <p>{Struk.Alamat}</p> : null}
                        {Struk.TeksKepala.map((teks) => (
                            <p key={teks}>{teks}</p>
                        ))}
                        {Struk.Npwp ? <p>NPWP {Struk.Npwp}</p> : null}
                    </header>
                    {Struk.Dibatalkan ? (
                        <p
                            role="status"
                            className="rounded-kontrol border border-bahaya px-2 py-1 text-center font-bold text-bahaya"
                        >
                            TRANSAKSI DIBATALKAN
                        </p>
                    ) : null}
                    <section className="border-t border-dashed border-garis pt-2">
                        <Baris kiri={Struk.Nomor} kanan="" />
                        <p>{FormatTanggalWaktu(Struk.Waktu)}</p>
                        {Struk.NamaKasir ? <p>Kasir: {Struk.NamaKasir}</p> : null}
                        {Struk.NamaPelanggan ? <p>Pelanggan: {Struk.NamaPelanggan}</p> : null}
                    </section>
                    <section className="flex flex-col gap-1 border-t border-dashed border-garis pt-2">
                        {Struk.Baris.map((b, i) => (
                            <div key={i}>
                                <p className="text-teks-utama">{b.NamaProduk}</p>
                                {b.Pilihan.length > 0 ? <p>+ {b.Pilihan.join(', ')}</p> : null}
                                <Baris
                                    kiri={`  ${FormatJumlah(b.Jumlah)} x ${FormatRupiah(b.HargaSatuan)}`}
                                    kanan={FormatRupiah(b.Total)}
                                />
                                {CekNol(b.Diskon) ? null : (
                                    <Baris kiri="  Diskon" kanan={`-${FormatRupiah(b.Diskon)}`} />
                                )}
                            </div>
                        ))}
                    </section>
                    <section className="flex flex-col gap-1 border-t border-dashed border-garis pt-2">
                        <Baris kiri="Subtotal" kanan={FormatRupiah(Struk.Subtotal)} />
                        {CekNol(Struk.TotalDiskon) ? null : (
                            <Baris kiri="Diskon" kanan={`-${FormatRupiah(Struk.TotalDiskon)}`} />
                        )}
                        {CekNol(Struk.BiayaLayanan) ? null : (
                            <Baris kiri="Biaya layanan" kanan={FormatRupiah(Struk.BiayaLayanan)} />
                        )}
                        {Struk.Pajak.map((p) => (
                            <Baris
                                key={p.Kode}
                                kiri={`${p.Kode} ${FormatJumlah(p.Tarif)}%`}
                                kanan={FormatRupiah(p.Jumlah)}
                            />
                        ))}
                        {CekNol(Struk.Pembulatan) ? null : (
                            <Baris kiri="Pembulatan" kanan={FormatRupiah(Struk.Pembulatan)} />
                        )}
                        <Baris kiri="TOTAL" kanan={FormatRupiah(Struk.TotalAkhir)} tebal />
                        {Struk.Pembayaran.map((b, i) => (
                            <Baris key={i} kiri={b.NamaMetode} kanan={FormatRupiah(b.Jumlah)} />
                        ))}
                        {CekNol(Struk.Kembalian) ? null : (
                            <Baris kiri="Kembalian" kanan={FormatRupiah(Struk.Kembalian)} />
                        )}
                        {Struk.TotalRetur ? (
                            <Baris kiri="Dikembalikan (retur)" kanan={`-${FormatRupiah(Struk.TotalRetur)}`} />
                        ) : null}
                    </section>
                    <footer className="border-t border-dashed border-garis pt-2 text-center">
                        {Struk.CatatanKaki ? <p>{Struk.CatatanKaki}</p> : null}
                        <p>{Struk.TeksPenutup ?? PENUTUP_BAWAAN}</p>
                    </footer>
                </article>
            </main>
        </>
    );
}

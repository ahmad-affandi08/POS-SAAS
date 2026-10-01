import { Head } from '@inertiajs/react';

import Tombol from '@/Komponen/Formulir/Tombol';
import TabelForm from '@/Komponen/TabelData/TabelForm';
import { TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { PropsNotaTagihan } from '@/Tipe/Pelanggan';

/** Ada sisa yang sudah lewat jatuh tempo (nilai desimal string bukan nol). */
function CekAda(nilai: string): boolean {
    return !/^0+(\.0+)?$/.test(nilai);
}

/**
 * Nota tagihan pelanggan (F-12, v3.37): semua piutang yang belum lunas satu pelanggan per tanggal bisnis hari ini,
 * untuk dicetak atau disimpan PDF lalu diberikan/dikirim ke pelanggan. Tanpa kerangka aplikasi supaya yang keluar di
 * kertas hanya notanya. Ini **bukan** dokumen pajak; nota ini hanya merangkum tagihan dari penjualan tempo.
 */
export default function HalamanNotaTagihan({
    Baris,
    TotalSisa,
    TotalLewat,
    Tanggal,
    Pelanggan,
    Usaha,
}: PropsNotaTagihan) {
    return (
        <main className="mx-auto flex max-w-4xl flex-col gap-6 bg-permukaan p-6 text-isi text-teks-utama print:p-0">
            <Head title={`Nota tagihan ${Pelanggan.Nama}`} />
            <div className="flex justify-end print:hidden">
                <Tombol onClick={() => window.print()}>Cetak atau simpan PDF</Tombol>
            </div>

            <header className="flex flex-wrap items-start justify-between gap-4 border-b border-garis pb-4">
                <div>
                    <p className="text-subjudul font-semibold">{Usaha.Nama ?? ''}</p>
                    {Usaha.Npwp ? <p className="text-teks-sekunder">NPWP {Usaha.Npwp}</p> : null}
                </div>
                <div className="text-right">
                    <JudulHalaman>Nota Tagihan</JudulHalaman>
                    <p>Per {FormatTanggal(Tanggal)}</p>
                </div>
            </header>

            <section>
                <h2 className="text-label font-semibold text-teks-sekunder">Kepada</h2>
                <p className="font-semibold">{Pelanggan.Nama}</p>
                {Pelanggan.Alamat ? <p className="whitespace-pre-line">{Pelanggan.Alamat}</p> : null}
                <p>{Pelanggan.NoHp}</p>
            </section>

            {Baris.length === 0 ? (
                <p className="text-teks-sekunder">Tidak ada tagihan yang belum lunas. Terima kasih.</p>
            ) : (
                <TabelForm label="Rincian tagihan" lebar="lebar">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Nomor</TableHead>
                            <TableHead scope="col">Tanggal</TableHead>
                            <TableHead scope="col">Jatuh tempo</TableHead>
                            <TableHead scope="col" className="text-right">
                                Tagihan
                            </TableHead>
                            <TableHead scope="col" className="text-right">
                                Dibayar/retur
                            </TableHead>
                            <TableHead scope="col" className="text-right">
                                Sisa
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Baris.map((b) => (
                            <TableRow key={b.Nomor}>
                                <TableCell className="font-mono">{b.Nomor}</TableCell>
                                <TableCell>{FormatTanggal(b.TanggalBisnis)}</TableCell>
                                <TableCell>
                                    {FormatTanggal(b.JatuhTempo)}
                                    {b.HariLewat > 0 ? (
                                        <span className="block text-keterangan font-semibold text-bahaya">
                                            Lewat {b.HariLewat} hari
                                        </span>
                                    ) : null}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{FormatRupiah(b.Jumlah)}</TableCell>
                                <TableCell className="text-right tabular-nums">{FormatRupiah(b.Dibayar)}</TableCell>
                                <TableCell className="text-right font-semibold tabular-nums">
                                    {FormatRupiah(b.Sisa)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell colSpan={5}>Total yang harus dibayar</TableCell>
                            <TableCell className="text-right font-bold tabular-nums">
                                {FormatRupiah(TotalSisa)}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </TabelForm>
            )}

            {CekAda(TotalLewat) ? (
                <p className="border-l-4 border-bahaya pl-3">
                    Sebesar <strong className="tabular-nums">{FormatRupiah(TotalLewat)}</strong> sudah lewat jatuh
                    tempo. Mohon segera dilunasi.
                </p>
            ) : null}

            <footer className="mt-8 grid grid-cols-2 gap-8 text-center">
                <div>
                    <p>Hormat kami</p>
                    <p className="mt-16 border-t border-garis pt-1">{Usaha.Nama ?? ''}</p>
                </div>
                <div>
                    <p>Diterima oleh</p>
                    <p className="mt-16 border-t border-garis pt-1">{Pelanggan.Nama}</p>
                </div>
            </footer>
        </main>
    );
}

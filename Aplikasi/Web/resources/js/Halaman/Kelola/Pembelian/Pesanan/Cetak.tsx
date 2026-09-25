import { Head } from '@inertiajs/react';

import { Button } from '@/Komponen/Ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatJumlahStok } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { PropsCetakPesanan } from '@/Tipe/Pembelian';

/**
 * F-04 fase 1: tampilan cetak pesanan pembelian (A4, cetak/simpan PDF lewat peramban). Tanpa kerangka aplikasi agar
 * rapi saat dicetak; rincian barang adalah rincian dokumen kecil (pengecualian `TabelData`, §25.2 no. 17).
 */
export default function HalamanCetakPesanan({ Pesanan, Baris, Usaha }: PropsCetakPesanan) {
    const ringkasan = [
        { label: 'Subtotal', nilai: Pesanan.Subtotal },
        { label: 'Diskon', nilai: Pesanan.Diskon },
        { label: Pesanan.TarifPpn ? `PPN ${FormatPersen(Pesanan.TarifPpn)}` : 'PPN', nilai: Pesanan.Pajak },
        { label: 'Ongkos kirim', nilai: Pesanan.Ongkir },
    ];

    return (
        <main className="mx-auto flex max-w-4xl flex-col gap-6 bg-permukaan p-6 text-isi text-teks-utama print:p-0">
            <Head title={`Pesanan ${Pesanan.Nomor}`} />
            <div className="flex justify-end print:hidden">
                <Button onClick={() => window.print()}>Cetak atau simpan PDF</Button>
            </div>

            <header className="flex flex-wrap items-start justify-between gap-4 border-b border-garis pb-4">
                <div>
                    <p className="text-subjudul font-semibold">{Usaha.Nama ?? ''}</p>
                    {Usaha.Npwp ? <p className="text-teks-sekunder">NPWP {Usaha.Npwp}</p> : null}
                </div>
                <div className="text-right">
                    <h1 className="text-judul font-semibold">Pesanan Pembelian</h1>
                    <p className="font-mono">{Pesanan.Nomor}</p>
                    <p>Tanggal {FormatTanggal(Pesanan.Tanggal)}</p>
                    {Pesanan.PerkiraanTiba ? <p>Perkiraan tiba {FormatTanggal(Pesanan.PerkiraanTiba)}</p> : null}
                </div>
            </header>

            <section className="grid gap-4 sm:grid-cols-2">
                <div>
                    <h2 className="text-label font-semibold text-teks-sekunder">Kepada</h2>
                    <p className="font-semibold">{Pesanan.Pemasok.Nama}</p>
                    {Pesanan.Pemasok.Alamat ? <p className="whitespace-pre-line">{Pesanan.Pemasok.Alamat}</p> : null}
                    {Pesanan.Pemasok.NoHp ? <p>{Pesanan.Pemasok.NoHp}</p> : null}
                    {Pesanan.Pemasok.Npwp ? <p>NPWP {Pesanan.Pemasok.Npwp}</p> : null}
                </div>
                <div>
                    <h2 className="text-label font-semibold text-teks-sekunder">Kirim ke</h2>
                    <p className="font-semibold">{Pesanan.NamaGudang}</p>
                    {Pesanan.NamaOutlet ? <p>{Pesanan.NamaOutlet}</p> : null}
                    <p>Termin: {Pesanan.TerminHari === 0 ? 'Tunai' : `${String(Pesanan.TerminHari)} hari`}</p>
                </div>
            </section>

            <Table className="text-left">
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col">Barang</TableHead>
                        <TableHead scope="col" className="text-right">
                            Jumlah
                        </TableHead>
                        <TableHead scope="col" className="text-right">
                            Harga
                        </TableHead>
                        <TableHead scope="col" className="text-right">
                            Diskon
                        </TableHead>
                        <TableHead scope="col" className="text-right">
                            Subtotal
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {Baris.map((b) => (
                        <TableRow key={b.Id}>
                            <TableCell className="whitespace-normal">
                                {b.NamaProduk}
                                {b.Sku ? <span className="block font-mono text-keterangan">{b.Sku}</span> : null}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {FormatJumlahStok(b.Jumlah, b.SimbolSatuan)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">{FormatRupiah(b.Harga)}</TableCell>
                            <TableCell className="text-right tabular-nums">{FormatRupiah(b.Diskon)}</TableCell>
                            <TableCell className="text-right tabular-nums">{FormatRupiah(b.Subtotal)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            <dl className="ml-auto flex w-full max-w-sm flex-col gap-1">
                {ringkasan.map((r) => (
                    <div key={r.label} className="flex justify-between gap-3">
                        <dt className="text-teks-sekunder">{r.label}</dt>
                        <dd className="tabular-nums">{FormatRupiah(r.nilai)}</dd>
                    </div>
                ))}
                <div className="flex justify-between gap-3 border-t border-garis pt-1 font-semibold">
                    <dt>Total</dt>
                    <dd className="tabular-nums">{FormatRupiah(Pesanan.Total)}</dd>
                </div>
            </dl>

            {Pesanan.Catatan ? (
                <section>
                    <h2 className="text-label font-semibold text-teks-sekunder">Catatan</h2>
                    <p className="whitespace-pre-line">{Pesanan.Catatan}</p>
                </section>
            ) : null}

            <footer className="mt-8 grid grid-cols-2 gap-8 text-center">
                <div>
                    <p>Dipesan oleh</p>
                    <p className="mt-16 border-t border-garis pt-1">{Pesanan.DibuatOleh ?? ''}</p>
                </div>
                <div>
                    <p>Disetujui oleh</p>
                    <p className="mt-16 border-t border-garis pt-1">{Pesanan.DisetujuiOleh ?? ''}</p>
                </div>
            </footer>
        </main>
    );
}

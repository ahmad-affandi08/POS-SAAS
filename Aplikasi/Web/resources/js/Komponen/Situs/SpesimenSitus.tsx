import { cn } from '@/Komponen/Ui/utils';

/**
 * Spesimen keluaran PAYOU untuk situs pemasaran (D-25): struk dan jurnal yang benar-benar dihasilkan produk,
 * dipakai sebagai jangkar visual hero dan blok gambar-teks.
 *
 * Kenapa bukan ilustrasi dan bukan tangkapan layar:
 * - §17.6.4 melarang ilustrasi dekoratif, dan ini bukan hiasan melainkan **contoh keluaran produk**;
 * - tangkapan layar tidak bisa dipakai di isi bawaan karena gambar situs tersimpan per pemasangan
 *   (`GambarSitus` dirujuk lewat Uuid), sedangkan `KontenSitusBawaan` adalah PHP statis;
 * - keduanya murni tipografi, jadi tetap terbaca tanpa gradien, bayangan, atau warna apa pun (§17.6.11).
 *
 * Angkanya contoh tetap (bukan klaim tentang usaha siapa pun) dan memakai font Mono + angka tabular seperti
 * struk sungguhan. Pembaca layar menerima satu kalimat lewat `role="img"`, bukan deretan angka.
 */

type PropsSpesimen = { className?: string | undefined };

const BARIS_STRUK = [
    { Nama: 'Kopi susu gula aren', Qty: '2', Harga: '36.000' },
    { Nama: 'Croissant butter', Qty: '1', Harga: '28.000' },
    { Nama: 'Air mineral 600ml', Qty: '1', Harga: '6.000' },
] as const;

/**
 * Struk termal: kop outlet, baris penjualan, PPN, total, dan penanda bahwa transaksi ini dibuat saat offline
 * lalu menunggu terkirim — pembeda utama PAYOU, ditandai dengan `Aksen` berteks `TeksUtama` (8,98:1).
 */
export function SpesimenStruk({ className }: PropsSpesimen) {
    return (
        <div
            role="img"
            aria-label="Contoh struk PAYOU: tiga baris penjualan, PPN, total Rp 77.000, dan penanda transaksi dibuat saat offline yang menunggu terkirim."
            className={cn(
                'text-isi w-full max-w-sm rounded-panel border border-garis bg-permukaan p-6 font-mono text-teks-utama tabular-nums',
                className,
            )}
        >
            <div className="flex flex-col items-center gap-0.5 border-b border-dashed border-garis pb-4 text-center">
                <p className="font-semibold">KOPI SENJA</p>
                <p className="text-keterangan text-teks-sekunder">Jl. Cendana 12, Yogyakarta</p>
                <p className="text-keterangan text-teks-sekunder">PJL/20260927/KSR1/0043</p>
            </div>
            <ul className="flex flex-col gap-2 border-b border-dashed border-garis py-4">
                {BARIS_STRUK.map((b) => (
                    <li key={b.Nama} className="flex items-start justify-between gap-3">
                        <span className="flex-1">
                            {b.Qty}× {b.Nama}
                        </span>
                        <span className="text-right">{b.Harga}</span>
                    </li>
                ))}
            </ul>
            <dl className="flex flex-col gap-1 border-b border-dashed border-garis py-4">
                <div className="flex justify-between gap-3">
                    <dt className="text-teks-sekunder">Subtotal</dt>
                    <dd>70.000</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-teks-sekunder">PPN 11%</dt>
                    <dd>7.000</dd>
                </div>
                <div className="flex justify-between gap-3 font-semibold">
                    <dt>TOTAL</dt>
                    <dd>Rp 77.000</dd>
                </div>
            </dl>
            <div className="flex flex-col gap-3 pt-4">
                <p className="flex justify-between gap-3 text-keterangan text-teks-sekunder">
                    <span>Tunai</span>
                    <span>100.000</span>
                </p>
                <p className="flex justify-between gap-3 text-keterangan text-teks-sekunder">
                    <span>Kembali</span>
                    <span>23.000</span>
                </p>
                {/* Penanda offline: warna tidak menanggung makna sendiri, teksnya yang menjelaskan. */}
                <p className="text-keterangan rounded-kontrol bg-aksen px-3 py-2 text-center font-semibold text-teks-utama">
                    Dibuat offline · menunggu terkirim
                </p>
            </div>
        </div>
    );
}

const BARIS_JURNAL = [
    { Akun: 'Kas', Kode: '1-1100', Debit: '77.000', Kredit: null },
    { Akun: 'Pendapatan Penjualan', Kode: '4-1000', Debit: null, Kredit: '70.000' },
    { Akun: 'PPN Keluaran', Kode: '2-1300', Debit: null, Kredit: '7.000' },
] as const;

/**
 * Jurnal otomatis dari struk di atas: memperlihatkan invariant Σ debit = Σ kredit, yang memang diuji di
 * test invariant keuangan. Ini pembeda yang sulit ditunjukkan lewat tangkapan layar.
 */
export function SpesimenJurnal({ className }: PropsSpesimen) {
    return (
        <div
            role="img"
            aria-label="Contoh jurnal otomatis PAYOU dari satu penjualan: Kas debit 77.000, Pendapatan Penjualan kredit 70.000, PPN Keluaran kredit 7.000, dengan total debit sama dengan total kredit."
            className={cn(
                'text-isi w-full rounded-panel border border-garis bg-permukaan p-6 text-teks-utama',
                className,
            )}
        >
            <p className="text-label mb-4 font-semibold text-teks-sekunder">Jurnal otomatis · PJL/20260927/KSR1/0043</p>
            <ul className="flex flex-col">
                {BARIS_JURNAL.map((b) => (
                    <li key={b.Kode} className="flex items-baseline gap-3 border-b border-garis py-3">
                        <span className="flex-1">
                            <span className="font-mono text-keterangan text-teks-sekunder">{b.Kode}</span> {b.Akun}
                        </span>
                        <span className="w-24 text-right font-mono tabular-nums">{b.Debit ?? '—'}</span>
                        <span className="w-24 text-right font-mono tabular-nums">{b.Kredit ?? '—'}</span>
                    </li>
                ))}
                <li className="flex items-baseline gap-3 py-3 font-semibold">
                    <span className="flex-1">Seimbang</span>
                    <span className="w-24 text-right font-mono tabular-nums">77.000</span>
                    <span className="w-24 text-right font-mono tabular-nums">77.000</span>
                </li>
            </ul>
        </div>
    );
}

export const SPESIMEN = { Struk: SpesimenStruk, Jurnal: SpesimenJurnal } as const;

export type NamaSpesimen = keyof typeof SPESIMEN;

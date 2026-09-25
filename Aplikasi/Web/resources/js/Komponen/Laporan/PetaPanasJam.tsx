import { cn } from '@/Komponen/Ui/utils';
import { FormatRupiah } from '@/Pustaka/Format';
import { AmbilMaksimum, HitungTingkatPanas, NamaHari } from '@/Pustaka/Laporan';
import type { SelJam } from '@/Tipe/Laporan';

/** Warna sel per tingkat: satu rona (brand), terang → gelap; tanpa gradien. Teks tidak di atas warna. */
const kelasTingkat = ['bg-permukaan-redup', 'bg-brand/15', 'bg-brand/35', 'bg-brand/60', 'bg-brand'] as const;
const jam = Array.from({ length: 24 }, (_, i) => i);

/**
 * Heatmap penjualan bersih per hari (Senin–Minggu) × jam lokal outlet. Setiap sel punya nama aksesibel & tooltip
 * (nilai & jumlah transaksi); legenda tingkat di bawah. Digulir di dalam wadahnya sendiri pada layar sempit.
 */
export default function PetaPanasJam({ sel }: { sel: SelJam[] }) {
    const peta = new Map(sel.map((s) => [`${String(s.Hari)}-${String(s.Jam)}`, s]));
    const maks = AmbilMaksimum(sel.map((s) => s.Bersih));

    return (
        <div className="flex flex-col gap-2">
            <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Heatmap penjualan per jam">
                <div className="grid min-w-[640px] grid-cols-[4.5rem_repeat(24,minmax(0,1fr))] gap-0.5 text-keterangan">
                    <span />
                    {jam.map((j) => (
                        <span key={j} className="text-center text-teks-sekunder tabular-nums" aria-hidden="true">
                            {j % 3 === 0 ? String(j).padStart(2, '0') : ''}
                        </span>
                    ))}
                    {NamaHari.map((namaHari, indeks) => (
                        <div key={namaHari} className="contents">
                            <span className="pr-2 text-teks-sekunder">{namaHari}</span>
                            {jam.map((j) => {
                                const isi = peta.get(`${String(indeks + 1)}-${String(j)}`);
                                const tingkat = isi ? HitungTingkatPanas(isi.Bersih, maks) : 0;
                                const keterangan = `${namaHari} ${String(j).padStart(2, '0')}.00: ${
                                    isi
                                        ? `${FormatRupiah(isi.Bersih)}, ${String(isi.JumlahTransaksi)} transaksi`
                                        : 'tidak ada penjualan'
                                }`;

                                return (
                                    <span
                                        key={j}
                                        role="img"
                                        aria-label={keterangan}
                                        title={keterangan}
                                        data-tingkat={tingkat}
                                        className={cn('h-6 rounded-sm', kelasTingkat[tingkat])}
                                    />
                                );
                            })}
                        </div>
                    ))}
                </div>
            </div>
            <div className="flex items-center gap-2 text-keterangan text-teks-sekunder" aria-hidden="true">
                <span>Sepi</span>
                {kelasTingkat.map((kelas) => (
                    <span key={kelas} className={cn('size-4 rounded-sm border border-garis', kelas)} />
                ))}
                <span>Ramai</span>
            </div>
        </div>
    );
}

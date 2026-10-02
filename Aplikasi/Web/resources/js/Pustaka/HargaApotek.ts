/**
 * Apotek (§9.5): saran Harga Jual Apotek (HJA) dari HNA (Harga Netto Apotek, harga faktur PBF termasuk PPN) + margin %.
 * HJA = HNA × (1 + margin/100), dibulatkan HalfUp ke Rp 100 terdekat. Hanya saran untuk mengisi harga jual; bukan mesin
 * harga baru (harga yang dipakai kasir tetap harga jual produk/daftar harga). BigInt berskala tetap, tanpa float
 * (CLAUDE.md #7).
 */
import { BacaDesimal, BulatkanDesimal, CekDesimalValid, CekSkalaMaksimal, TulisDesimal } from './HitungDesimal';

/** Kelipatan pembulatan HJA dalam sen (Rp 100). */
const kelipatanSen = 10000n;

/** Margin maksimal yang diterima (persen). */
const marginMaksimal = 1000n;

/**
 * Saran HJA sebagai string desimal Rupiah ("13800.00"), atau null bila HNA/margin belum valid (kosong, negatif, lebih
 * dari 2 desimal, margin > 1000%).
 */
export function HitungSaranHja(hna: string, persenMargin: string): string | null {
    const teksHna = hna.trim();
    const teksMargin = persenMargin.trim().replace(',', '.');

    if (!CekDesimalValid(teksHna) || !CekDesimalValid(teksMargin)) return null;
    if (!CekSkalaMaksimal(teksHna, 2) || !CekSkalaMaksimal(teksMargin, 2)) return null;

    const sen = BacaDesimal(BulatkanDesimal(teksHna, 2)).nilai;
    const basisPoin = BacaDesimal(BulatkanDesimal(teksMargin, 2)).nilai;

    if (sen <= 0n || basisPoin < 0n || basisPoin > marginMaksimal * 100n) return null;

    // sen × (10000 + bp) / 10000 = HJA dalam sen; dibagi lagi per Rp 100 (10000 sen), HalfUp.
    const pembilang = sen * (10000n + basisPoin);
    const penyebut = 10000n * kelipatanSen;
    const ratusan = (pembilang * 2n + penyebut) / (penyebut * 2n);

    return TulisDesimal(ratusan * kelipatanSen, 2);
}

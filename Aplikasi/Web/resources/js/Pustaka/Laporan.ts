import { BacaDesimal, TulisDesimal } from '@/Pustaka/HitungDesimal';

/**
 * Bantuan tampilan laporan F-14a. Uang tetap string desimal; perbandingan & tingkat heatmap dihitung dengan BigInt
 * (CLAUDE.md #7). Hanya tinggi batang grafik yang memakai number (posisi piksel, bukan nilai uang).
 */

function KeSkala(teks: string, skala: number): bigint {
    const d = BacaDesimal(teks);

    return d.skala <= skala ? d.nilai * 10n ** BigInt(skala - d.skala) : d.nilai / 10n ** BigInt(d.skala - skala);
}

/**
 * Perubahan persen `sekarang` terhadap `pembanding` dengan satu desimal, mis. "+12,5%" atau "−3%". Null bila
 * pembanding nol (tidak bisa dibandingkan).
 */
export function HitungPerubahanPersen(sekarang: string, pembanding: string): string | null {
    const a = KeSkala(sekarang, 2);
    const b = KeSkala(pembanding, 2);

    if (b === 0n) {
        return null;
    }

    const selisih = (a - b) * 1000n;
    const penyebut = b < 0n ? -b : b;
    const negatif = selisih < 0n;
    const mutlak = negatif ? -selisih : selisih;
    const persepuluh = (mutlak * 2n + penyebut) / (penyebut * 2n);
    const teks = TulisDesimal(persepuluh, 1).replace(/\.0$/, '').replace('.', ',');

    if (persepuluh === 0n) {
        return '0%';
    }

    return `${negatif ? '−' : '+'}${teks}%`;
}

/** Tingkat warna heatmap 0–4 (0 = tanpa penjualan) dari nilai terhadap nilai terbesar, dengan BigInt. */
export function HitungTingkatPanas(nilai: string, maks: string): 0 | 1 | 2 | 3 | 4 {
    const n = KeSkala(nilai, 2);
    const m = KeSkala(maks, 2);

    if (n <= 0n || m <= 0n) {
        return 0;
    }

    const tingkat = (n * 4n + m - 1n) / m;

    return (tingkat >= 4n ? 4 : tingkat <= 1n ? 1 : Number(tingkat)) as 1 | 2 | 3 | 4;
}

/** Nilai terbesar dari daftar string desimal ("0.00" bila kosong). */
export function AmbilMaksimum(daftar: readonly string[]): string {
    return daftar.reduce((maks, nilai) => (KeSkala(nilai, 2) > KeSkala(maks, 2) ? nilai : maks), daftar[0] ?? '0.00');
}

/** Tinggi batang grafik (hanya posisi gambar). */
export function AmbilTinggiGrafik(nilai: string): number {
    return Number(nilai);
}

/** Query string saring laporan tanpa nilai kosong. */
export function BuatQueryLaporan(saring: Record<string, string>): string {
    const parameter = new URLSearchParams();

    for (const [kunci, nilai] of Object.entries(saring)) {
        if (nilai !== '') {
            parameter.set(kunci, nilai);
        }
    }

    return parameter.toString();
}

export const NamaHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as const;

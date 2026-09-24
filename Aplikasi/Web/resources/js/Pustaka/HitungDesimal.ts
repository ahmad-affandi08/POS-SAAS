/**
 * Aritmetika desimal persediaan F-05a berbasis BigInt berskala tetap (CLAUDE.md #7, DesainF05a C.3 & E).
 *
 * Semua nilai adalah string desimal polos seperti dari/ke server: uang "12345.68", jumlah "10.0000",
 * HPP "1234.568000". Tidak pernah diubah ke number/float. Dipakai untuk pratinjau di peramban
 * ("perkiraan"); server tetap sumber kebenaran.
 */

/** Skala kolom Backend: uang DECIMAL(18,2), jumlah DECIMAL(18,4), HPP per satuan DECIMAL(19,6). */
export const SkalaUang = 2;
export const SkalaJumlah = 4;
export const SkalaHpp = 6;

export type Desimal = { nilai: bigint; skala: number };

const polaDesimal = /^(-?)(\d+)(?:\.(\d+))?$/;

/** True bila teks string desimal polos: "12", "0.5", "-3.25". Tidak menerima "1,5", "1e3", atau "". */
export function CekDesimalValid(teks: string): boolean {
    return polaDesimal.test(teks.trim());
}

/** "−12.50" → { nilai: -1250n, skala: 2 }. Lempar Error bila tidak valid. */
export function BacaDesimal(teks: string): Desimal {
    const cocok = polaDesimal.exec(teks.trim());

    if (!cocok) {
        throw new Error(`Desimal tidak valid: "${teks}"`);
    }

    const [, tanda = '', bulat = '0', pecahan = ''] = cocok;
    const angka = BigInt(`${bulat}${pecahan}`);

    return { nilai: tanda === '-' ? -angka : angka, skala: pecahan.length };
}

/** Tulis bilangan berskala sebagai string desimal polos: (-1250n, 2) → "-12.50"; (0n, 2) → "0.00". */
export function TulisDesimal(nilai: bigint, skala: number): string {
    const negatif = nilai < 0n;
    const mutlak = (negatif ? -nilai : nilai).toString().padStart(skala + 1, '0');
    const bulat = skala === 0 ? mutlak : mutlak.slice(0, -skala);
    const pecahan = skala === 0 ? '' : mutlak.slice(-skala);

    return `${negatif ? '-' : ''}${bulat}${pecahan === '' ? '' : `.${pecahan}`}`;
}

function NaikkanSkala(d: Desimal, skala: number): bigint {
    return d.nilai * 10n ** BigInt(skala - d.skala);
}

/** Bagi dengan pembulatan HalfUp (setengah menjauhi nol), sama dengan `RoundingMode::HALF_UP` brick/math. */
function BagiHalfUp(pembilang: bigint, penyebut: bigint): bigint {
    const negatif = pembilang < 0n !== penyebut < 0n;
    const p = pembilang < 0n ? -pembilang : pembilang;
    const q = penyebut < 0n ? -penyebut : penyebut;
    const hasil = (p * 2n + q) / (q * 2n);

    return negatif ? -hasil : hasil;
}

/** Ubah ke `skala` digit pecahan dengan HalfUp: ("1234.5678", 2) → "1234.57"; ("5", 2) → "5.00". */
export function BulatkanDesimal(teks: string, skala: number): string {
    const d = BacaDesimal(teks);

    if (d.skala <= skala) {
        return TulisDesimal(NaikkanSkala(d, skala), skala);
    }

    return TulisDesimal(BagiHalfUp(d.nilai, 10n ** BigInt(d.skala - skala)), skala);
}

/** -1 bila a < b, 0 bila sama ("12" = "12.0000"), 1 bila a > b. */
export function BandingkanDesimal(a: string, b: string): -1 | 0 | 1 {
    const da = BacaDesimal(a);
    const db = BacaDesimal(b);
    const skala = Math.max(da.skala, db.skala);
    const selisih = NaikkanSkala(da, skala) - NaikkanSkala(db, skala);

    return selisih < 0n ? -1 : selisih > 0n ? 1 : 0;
}

/** Tanda bilangan: -1, 0, atau 1. "-0.00" dianggap 0. */
export function AmbilTandaDesimal(teks: string): -1 | 0 | 1 {
    return BandingkanDesimal(teks, '0');
}

/** Jumlahkan tanpa pembulatan pada skala terbesar: ["333.33", "333.33", "333.34"] → "1000.00"; [] → "0". */
export function JumlahkanDesimal(daftar: readonly string[]): string {
    const semua = daftar.map(BacaDesimal);
    const skala = semua.reduce((maks, d) => Math.max(maks, d.skala), 0);
    const total = semua.reduce((jumlah, d) => jumlah + NaikkanSkala(d, skala), 0n);

    return TulisDesimal(total, skala);
}

/** a − b tanpa pembulatan: ("10", "12.5") → "-2.5". */
export function KurangiDesimal(a: string, b: string): string {
    const db = BacaDesimal(b);

    return JumlahkanDesimal([a, TulisDesimal(-db.nilai, db.skala)]);
}

/** Nilai mutlak: "-3.50" → "3.50". */
export function MutlakDesimal(teks: string): string {
    const d = BacaDesimal(teks);

    return TulisDesimal(d.nilai < 0n ? -d.nilai : d.nilai, d.skala);
}

/** a × b dibulatkan HalfUp ke `skala`: ("3", "333.333333", 2) → "1000.00". */
export function KalikanDesimal(a: string, b: string, skala: number): string {
    const da = BacaDesimal(a);
    const db = BacaDesimal(b);
    const hasil = BagiHalfUp(da.nilai * db.nilai * 10n ** BigInt(skala), 10n ** BigInt(da.skala + db.skala));

    return TulisDesimal(hasil, skala);
}

/** True bila string desimal polos dengan paling banyak `skala` digit pecahan ("1.25" untuk skala 2). */
export function CekSkalaMaksimal(teks: string, skala: number): boolean {
    return CekDesimalValid(teks) && BacaDesimal(teks).skala <= skala;
}

/** True bila bilangan bulat ("12", "12.0000"). */
export function CekDesimalBulat(teks: string): boolean {
    const cocok = polaDesimal.exec(teks.trim());

    return cocok !== null && /^0*$/.test(cocok[3] ?? '');
}

/**
 * Nilai satu baris stok awal: `Nilai(q, c) = (|q| × c)` dibulatkan HalfUp ke 2 desimal (DesainF05a C.3,
 * `AritmetikaHpp::Nilai`). Null bila jumlah atau HPP belum valid (masih diketik).
 */
export function HitungNilaiBaris(jumlah: string, hppSatuan: string): string | null {
    if (!CekDesimalValid(jumlah) || !CekDesimalValid(hppSatuan)) {
        return null;
    }

    return KalikanDesimal(MutlakDesimal(jumlah), hppSatuan, SkalaUang);
}

/** Total perkiraan dokumen = Σ Nilai baris (baris yang belum valid dilewati). Selalu skala 2. */
export function HitungTotalNilai(baris: readonly { Jumlah: string; HppSatuan: string }[]): string {
    const nilai = baris
        .map((item) => HitungNilaiBaris(item.Jumlah, item.HppSatuan))
        .filter((item): item is string => item !== null);

    return BulatkanDesimal(JumlahkanDesimal(nilai), SkalaUang);
}

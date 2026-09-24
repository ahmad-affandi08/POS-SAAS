/**
 * Masukan & olahan kuantitas/persen sebagai teks (F-03, CLAUDE.md #7).
 *
 * Tampilan memakai format Indonesia ("1.250,5"), server menerima string desimal polos ("1250.5",
 * regex Kuantitas `^\d{1,14}(\.\d{1,4})?$`, Persen `^\d{1,3}(\.\d{1,6})?$`). Aritmetika memakai
 * BigInt berskala tetap: tidak pernah number/float.
 */

/** Skala desimal kuantitas di Backend (DECIMAL(18,4)). */
export const SkalaKuantitas = 4;
/** Skala desimal persen di Backend (DECIMAL(9,6)). */
export const SkalaPersen = 6;

type OpsiMasukanJumlah = {
    /** Jumlah digit pecahan yang boleh diketik. 0 = bilangan bulat saja (satuan tanpa desimal). */
    desimal?: number;
    /** Jumlah digit bulat maksimal. */
    digitBulat?: number;
};

function BersihkanMasukanJumlah(teks: string): string {
    // Titik = pemisah ribuan (format Indonesia), spasi & "%" diabaikan. Koma = pemisah desimal.
    return teks.replace(/[\s.%]/g, '');
}

function BuatPolaMasukan({ desimal = SkalaKuantitas, digitBulat = 14 }: OpsiMasukanJumlah): RegExp {
    return desimal > 0
        ? new RegExp(`^(\\d{0,${String(digitBulat)}})(?:,(\\d{0,${String(desimal)}}))?$`)
        : new RegExp(`^(\\d{0,${String(digitBulat)}})$`);
}

/** True bila teks bisa dibaca sebagai kuantitas: "1.250,5", "12", "0,25", "" (kosong) atau ketikan setengah jadi "3,". */
export function CekMasukanJumlahValid(teks: string, opsi: OpsiMasukanJumlah = {}): boolean {
    return BuatPolaMasukan(opsi).test(BersihkanMasukanJumlah(teks));
}

/** "1.250,50" → "1250.5"; "0,0000" → "0"; "3," → "3"; "" → "". Lempar Error bila tidak valid. */
export function NormalisasiMasukanJumlah(teks: string, opsi: OpsiMasukanJumlah = {}): string {
    const cocok = BuatPolaMasukan(opsi).exec(BersihkanMasukanJumlah(teks));

    if (!cocok) {
        throw new Error(`Masukan jumlah tidak valid: "${teks}"`);
    }

    const [, bulat = '', pecahan = ''] = cocok;

    if (bulat === '' && pecahan === '') {
        return '';
    }

    const bulatBersih = (bulat === '' ? '0' : bulat).replace(/^0+(?=\d)/, '');
    const pecahanBersih = pecahan.replace(/0+$/, '');

    return pecahanBersih === '' ? bulatBersih : `${bulatBersih}.${pecahanBersih}`;
}

/** String desimal server → teks tampilan: "1250.5000" → "1.250,5"; "18.0000" → "18"; "" → "". */
export function FormatMasukanJumlah(nilai: string): string {
    const cocok = /^(-?)(\d+)(?:\.(\d+))?$/.exec(nilai.trim());

    if (!cocok) {
        return nilai;
    }

    const [, tanda = '', bulat = '0', pecahan = ''] = cocok;
    const bulatBersih = bulat.replace(/^0+(?=\d)/, '');
    const denganTitik = bulatBersih.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const pecahanRingkas = pecahan.replace(/0+$/, '');
    const negatif = tanda === '-' && (bulatBersih !== '0' || pecahanRingkas !== '');

    return `${negatif ? '−' : ''}${denganTitik}${pecahanRingkas === '' ? '' : `,${pecahanRingkas}`}`;
}

/** Kuantitas dengan simbol satuan: ("0.5000", "kg") → "0,5 kg". */
export function FormatJumlahSatuan(nilai: string, simbol: string): string {
    return `${FormatMasukanJumlah(nilai)} ${simbol}`.trim();
}

// ---------------------------------------------------------------------------------------------
// Aritmetika desimal berbasis BigInt (skala tetap). Dipakai untuk validasi & pratinjau di peramban;
// server tetap sumber kebenaran.
// ---------------------------------------------------------------------------------------------

type Desimal = { nilai: bigint; skala: number };

const polaDesimal = /^(-?)(\d+)(?:\.(\d+))?$/;

/** True bila teks adalah string desimal polos yang dikirim/diterima server ("12", "0.5", "-3.25"). */
export function CekDesimalValid(nilai: string): boolean {
    return polaDesimal.test(nilai.trim());
}

function BacaDesimal(nilai: string): Desimal {
    const cocok = polaDesimal.exec(nilai.trim());

    if (!cocok) {
        throw new Error(`Desimal tidak valid: "${nilai}"`);
    }

    const [, tanda = '', bulat = '0', pecahan = ''] = cocok;
    const angka = BigInt(`${bulat}${pecahan}`);

    return { nilai: tanda === '-' ? -angka : angka, skala: pecahan.length };
}

function SamakanSkala(a: Desimal, skala: number): bigint {
    return a.nilai * 10n ** BigInt(skala - a.skala);
}

function TulisDesimal(nilai: bigint, skala: number): string {
    const negatif = nilai < 0n;
    const mutlak = (negatif ? -nilai : nilai).toString().padStart(skala + 1, '0');
    const bulat = skala === 0 ? mutlak : mutlak.slice(0, -skala);
    const pecahan = skala === 0 ? '' : mutlak.slice(-skala);

    return `${negatif ? '-' : ''}${bulat}${pecahan === '' ? '' : `.${pecahan}`}`;
}

/** Bandingkan dua string desimal: -1 bila a < b, 0 bila sama, 1 bila a > b ("12" vs "12.0000" = 0). */
export function BandingkanDesimal(a: string, b: string): -1 | 0 | 1 {
    const da = BacaDesimal(a);
    const db = BacaDesimal(b);
    const skala = Math.max(da.skala, db.skala);
    const selisih = SamakanSkala(da, skala) - SamakanSkala(db, skala);

    return selisih < 0n ? -1 : selisih > 0n ? 1 : 0;
}

/** True bila string desimal > 0. */
export function CekDesimalPositif(nilai: string): boolean {
    return CekDesimalValid(nilai) && BandingkanDesimal(nilai, '0') === 1;
}

/** True bila string desimal bilangan bulat ("12", "12.0000"). */
export function CekDesimalBulat(nilai: string): boolean {
    const cocok = polaDesimal.exec(nilai.trim());

    return cocok !== null && /^0*$/.test(cocok[3] ?? '');
}

/** Jumlahkan string desimal tanpa pembulatan: ["33.333333", "33.333333", "33.333334"] → "100.000000". */
export function JumlahkanDesimal(daftar: string[]): string {
    const semua = daftar.map(BacaDesimal);
    const skala = semua.reduce((maks, d) => Math.max(maks, d.skala), 0);
    const total = semua.reduce((jumlah, d) => jumlah + SamakanSkala(d, skala), 0n);

    return TulisDesimal(total, skala);
}

/** Bagi dua bilangan skala-tetap dengan pembulatan setengah ke atas (HalfUp, menjauhi nol). */
function BagiBulatkan(pembilang: bigint, penyebut: bigint): bigint {
    const negatif = pembilang < 0n !== penyebut < 0n;
    const p = pembilang < 0n ? -pembilang : pembilang;
    const q = penyebut < 0n ? -penyebut : penyebut;
    const hasil = (p * 2n + q) / (q * 2n);

    return negatif ? -hasil : hasil;
}

/**
 * Pratinjau jumlah kotor bahan resep (BR-03.5, keputusan lead atas DesainF03 H.7):
 * `JumlahKotor = JumlahDasar ÷ (1 − PersenSusut/100)`, PersenSusut di rentang [0, 100).
 * Hasil berskala `skala` (bawaan 4) dibulatkan HalfUp. Null bila masukan tidak valid atau susut ≥ 100.
 */
export function HitungJumlahKotor(jumlahDasar: string, persenSusut: string, skala = SkalaKuantitas): string | null {
    if (!CekDesimalValid(jumlahDasar) || !CekDesimalValid(persenSusut)) {
        return null;
    }

    if (BandingkanDesimal(persenSusut, '0') < 0 || BandingkanDesimal(persenSusut, '100') >= 0) {
        return null;
    }

    // Kotor = Dasar × 100 ÷ (100 − susut), semua di skala bersama.
    const dasar = BacaDesimal(jumlahDasar);
    const susut = BacaDesimal(persenSusut);
    const skalaSusut = susut.skala;
    const seratus = 100n * 10n ** BigInt(skalaSusut);
    const penyebut = seratus - susut.nilai;
    // pembilang di skala (dasar.skala + skalaSusut); hasil diinginkan di `skala`.
    const pembilang = dasar.nilai * seratus * 10n ** BigInt(skala);
    const hasil = BagiBulatkan(pembilang, penyebut * 10n ** BigInt(dasar.skala));

    return TulisDesimal(hasil, skala);
}

/** Kalikan string desimal dengan pembulatan HalfUp ke `skala`: ("18", "250") → "4500.0000". */
export function KalikanDesimal(a: string, b: string, skala = SkalaKuantitas): string {
    const da = BacaDesimal(a);
    const db = BacaDesimal(b);
    const hasil = BagiBulatkan(da.nilai * db.nilai * 10n ** BigInt(skala), 10n ** BigInt(da.skala + db.skala));

    return TulisDesimal(hasil, skala);
}

/** Bulatkan string desimal ke `skala` digit dengan HalfUp: ("7800.125000", 2) → "7800.13". Untuk tampilan HPP. */
export function BulatkanDesimal(nilai: string, skala: number): string {
    const d = BacaDesimal(nilai);

    if (d.skala <= skala) {
        return TulisDesimal(SamakanSkala(d, skala), skala);
    }

    return TulisDesimal(BagiBulatkan(d.nilai, 10n ** BigInt(d.skala - skala)), skala);
}

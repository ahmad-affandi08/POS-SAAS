/**
 * Masukan uang & persen dari pengguna (F-01, CLAUDE.md #7).
 *
 * Tampilan memakai format Indonesia ("15.000"), sedangkan server menerima string desimal polos
 * tanpa pemisah ribuan ("15000", regex `^\d{1,16}(\.\d{1,2})?$`). Semua olahan di sini murni teks:
 * tidak ada konversi ke number/float.
 */

// Setelah "Rp", spasi, dan titik ribuan dibuang: 1–16 digit, boleh diikuti ",dd" (sen, tepat 2 digit).
const polaMasukanUang = /^(?:(\d{1,16})(?:,(\d{2}))?)?$/;
const polaDesimalServer = /^(\d+)(?:\.(\d{1,2}))?$/;

function BersihkanMasukanUang(teks: string): string {
    return teks.replace(/^\s*rp\.?/i, '').replace(/[\s.]/g, '');
}

/** True bila teks bisa dibaca sebagai nominal Rupiah: "Rp 15.000", "15000", "15.000,00", atau kosong. */
export function CekMasukanUangValid(teks: string): boolean {
    return polaMasukanUang.test(BersihkanMasukanUang(teks));
}

/**
 * "Rp 15.000" → "15000"; "1.250.000,50" → "1250000.50"; "" → "".
 * Koma desimal satu digit ("12,5") ditolak karena ambigu untuk Rupiah; lempar Error untuk teks tidak valid.
 */
export function NormalisasiMasukanUang(teks: string): string {
    const cocok = polaMasukanUang.exec(BersihkanMasukanUang(teks));

    if (!cocok) {
        throw new Error(`Masukan uang tidak valid: "${teks}"`);
    }

    const [, bulat = '', sen = ''] = cocok;

    if (bulat === '') {
        return '';
    }

    const bulatBersih = bulat.replace(/^0+(?=\d)/, '');

    return sen === '' || sen === '00' ? bulatBersih : `${bulatBersih}.${sen}`;
}

/** String desimal polos → teks tampilan tanpa "Rp": "1250000" → "1.250.000", "22000.00" → "22.000". */
export function FormatMasukanUang(nilai: string): string {
    const cocok = polaDesimalServer.exec(nilai.trim());

    if (!cocok) {
        return nilai;
    }

    const [, bulat = '0', sen = ''] = cocok;
    const bulatBersih = bulat.replace(/^0+(?=\d)/, '');
    const denganTitik = bulatBersih.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const senDuaDigit = sen.padEnd(2, '0');

    return sen === '' || senDuaDigit === '00' ? denganTitik : `${denganTitik},${senDuaDigit}`;
}

/** Jumlah digit di kiri posisi kursor; dipakai agar kursor tidak meloncat saat titik ribuan disisipkan. */
export function HitungDigitSebelumKursor(teks: string, posisi: number): number {
    return teks.slice(0, posisi).replace(/\D/g, '').length;
}

/** Posisi kursor di teks terformat tepat setelah digit ke-`jumlahDigit`. */
export function HitungPosisiKursor(teksTampil: string, jumlahDigit: number): number {
    if (jumlahDigit <= 0) {
        return 0;
    }

    let digit = 0;

    for (let indeks = 0; indeks < teksTampil.length; indeks += 1) {
        if (/\d/.test(teksTampil.charAt(indeks))) {
            digit += 1;

            if (digit === jumlahDigit) {
                return indeks + 1;
            }
        }
    }

    return teksTampil.length;
}

/** Persen dari pengguna ("7,5", "10 %") → string desimal server ("7.5", "10"). Tanpa number. */
export function NormalisasiMasukanPersen(teks: string): string {
    return teks.replace(/[\s%]/g, '').replace(',', '.');
}

/** Persen dari server ("5.00") → teks tampilan dengan koma desimal ("5,00"). */
export function FormatMasukanPersen(nilai: string): string {
    return nilai.replace('.', ',');
}

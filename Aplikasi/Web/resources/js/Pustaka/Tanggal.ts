/**
 * Bantuan tanggal kalender untuk pemilih tanggal back-office (PRD §17.6.7: tampilan `22/09/2026`).
 * Nilai yang dikirim ke server selalu string `TTTT-BB-HH` (atau `TTTT-BB-HHTjj:mm` untuk tanggal + jam);
 * objek `Date` hanya dipakai lokal di peramban (tanpa geser zona waktu).
 */

const polaIso = /^(\d{4})-(\d{2})-(\d{2})$/;
const polaTampil = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/;
const polaJam = /^([01]?\d|2[0-3])[:.]([0-5]\d)$/;

function BuatTanggalSah(tahun: number, bulan: number, hari: number): Date | undefined {
    const tanggal = new Date(tahun, bulan - 1, hari);

    return tanggal.getFullYear() === tahun && tanggal.getMonth() === bulan - 1 && tanggal.getDate() === hari
        ? tanggal
        : undefined;
}

/** `TTTT-BB-HH` → Date lokal, atau undefined bila belum lengkap/tidak valid. */
export function UraiTanggal(nilai: string): Date | undefined {
    const cocok = polaIso.exec(nilai.trim());

    return cocok === null ? undefined : BuatTanggalSah(Number(cocok[1]), Number(cocok[2]), Number(cocok[3]));
}

/** Date lokal → `TTTT-BB-HH`. */
export function TulisTanggal(tanggal: Date): string {
    const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
    const hari = String(tanggal.getDate()).padStart(2, '0');

    return `${String(tanggal.getFullYear())}-${bulan}-${hari}`;
}

/** `TTTT-BB-HH` → `HH/BB/TTTT` untuk ditampilkan di isian; string kosong bila tidak valid. */
export function FormatTanggalIsian(nilai: string): string {
    const tanggal = UraiTanggal(nilai);

    if (tanggal === undefined) {
        return '';
    }

    const hari = String(tanggal.getDate()).padStart(2, '0');
    const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');

    return `${hari}/${bulan}/${String(tanggal.getFullYear())}`;
}

/**
 * Teks ketikan pengguna → `TTTT-BB-HH`. Menerima `24/09/2026`, `24-9-2026`, `24.09.2026`, dan `2026-09-24`.
 * Mengembalikan undefined bila belum lengkap atau bukan tanggal yang ada (mis. 30/02/2026).
 */
export function UraiTeksTanggal(teks: string): string | undefined {
    const bersih = teks.trim();
    const iso = UraiTanggal(bersih);

    if (iso !== undefined) {
        return TulisTanggal(iso);
    }

    const cocok = polaTampil.exec(bersih);

    if (cocok === null) {
        return undefined;
    }

    const tanggal = BuatTanggalSah(Number(cocok[3]), Number(cocok[2]), Number(cocok[1]));

    return tanggal === undefined ? undefined : TulisTanggal(tanggal);
}

/** Teks jam `9:05`, `09.05`, `09:05` → `09:05`; undefined bila tidak valid. */
export function UraiTeksJam(teks: string): string | undefined {
    const cocok = polaJam.exec(teks.trim());

    return cocok === null ? undefined : `${(cocok[1] ?? '').padStart(2, '0')}:${cocok[2] ?? ''}`;
}

/** Tanggal di luar batas `min`/`max` (string `TTTT-BB-HH`, perbandingan leksikal aman untuk format ini). */
export function CekDiLuarBatas(nilai: string, min?: string, max?: string): boolean {
    return (min !== undefined && min !== '' && nilai < min) || (max !== undefined && max !== '' && nilai > max);
}

export type PresetRentang = { label: string; nilai: string };

/** Preset rentang tanggal §17.6.5 (tanggal lokal peramban), nilai `dari..sampai`. */
export function BuatPresetTanggal(hariIni = new Date()): PresetRentang[] {
    const tahun = hariIni.getFullYear();
    const bulan = hariIni.getMonth();
    const tanggal = hariIni.getDate();
    const Rentang = (dari: Date, sampai: Date) => `${TulisTanggal(dari)}..${TulisTanggal(sampai)}`;

    return [
        { label: 'Hari ini', nilai: Rentang(hariIni, hariIni) },
        { label: 'Kemarin', nilai: Rentang(new Date(tahun, bulan, tanggal - 1), new Date(tahun, bulan, tanggal - 1)) },
        { label: '7 hari terakhir', nilai: Rentang(new Date(tahun, bulan, tanggal - 6), hariIni) },
        { label: '30 hari terakhir', nilai: Rentang(new Date(tahun, bulan, tanggal - 29), hariIni) },
        { label: 'Bulan ini', nilai: Rentang(new Date(tahun, bulan, 1), new Date(tahun, bulan + 1, 0)) },
        { label: 'Bulan lalu', nilai: Rentang(new Date(tahun, bulan - 1, 1), new Date(tahun, bulan, 0)) },
        { label: 'Tahun ini', nilai: Rentang(new Date(tahun, 0, 1), new Date(tahun, 11, 31)) },
    ];
}

/** `dari..sampai` → `[dari, sampai]` (salah satu boleh kosong). */
export function PecahRentang(nilai: string): [string, string] {
    const [dari = '', sampai = ''] = nilai.split('..');

    return [dari, sampai];
}

/** `[dari, sampai]` → `dari..sampai`, atau string kosong bila keduanya kosong. */
export function GabungRentang(dari: string, sampai: string): string {
    return dari === '' && sampai === '' ? '' : `${dari}..${sampai}`;
}

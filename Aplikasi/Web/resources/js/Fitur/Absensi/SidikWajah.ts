/**
 * Fungsi murni absensi web (F-18 bagian 4, D-37) yang bisa diuji tanpa kamera & model wajah.
 *
 * Server mencocokkan sidik wajah sebagai bilangan bulat (deskriptor × 10.000) supaya hitungannya tetap desimal tanpa
 * pecahan biner (`PencocokWajah`). Nilai dibatasi ±100.000 sesuai validasi server.
 */
export const SKALA_SIDIK = 10_000;
export const BATAS_NILAI_SIDIK = 100_000;

/** Ambang skor model di HP sebelum wajah dianggap hidup (bukan foto/layar). Keputusan cocok tetap di server. */
export const AMBANG_WAJAH_ASLI = 0.5;
export const AMBANG_WAJAH_HIDUP = 0.5;

export function KeSidikWajah(deskriptor: readonly number[]): number[] {
    return deskriptor.map((nilai) => {
        const bulat = Math.round(nilai * SKALA_SIDIK);

        return Math.max(-BATAS_NILAI_SIDIK, Math.min(BATAS_NILAI_SIDIK, Number.isFinite(bulat) ? bulat : 0));
    });
}

/** Koordinat Geolocation API → teks derajat desimal 7 angka (format yang diterima server). */
export function KeTeksKoordinat(derajat: number): string {
    return derajat.toFixed(7);
}

/** Akurasi GPS dibulatkan ke atas ke meter (server menolak akurasi lebih buruk dari radius outlet). */
export function KeAkurasiMeter(akurasi: number): number {
    return Math.max(0, Math.ceil(akurasi));
}

export type PenilaianWajah =
    | { Lolos: true }
    | { Lolos: false; Alasan: 'TidakAdaWajah' | 'BanyakWajah' | 'BukanWajahAsli' | 'BelumKedip' | 'SidikKosong' };

/**
 * Menilai satu hasil deteksi: tepat satu wajah, skor wajah asli & hidup cukup, sudah berkedip selama pemindaian,
 * dan model menghasilkan sidik wajah.
 */
export function NilaiWajah(
    wajah: readonly { real?: number; live?: number; embedding?: number[] }[],
    sudahKedip: boolean,
): PenilaianWajah {
    if (wajah.length === 0) {
        return { Lolos: false, Alasan: 'TidakAdaWajah' };
    }

    if (wajah.length > 1) {
        return { Lolos: false, Alasan: 'BanyakWajah' };
    }

    const satu = wajah[0];

    if ((satu?.real ?? 0) < AMBANG_WAJAH_ASLI || (satu?.live ?? 0) < AMBANG_WAJAH_HIDUP) {
        return { Lolos: false, Alasan: 'BukanWajahAsli' };
    }

    if (!sudahKedip) {
        return { Lolos: false, Alasan: 'BelumKedip' };
    }

    if ((satu?.embedding?.length ?? 0) === 0) {
        return { Lolos: false, Alasan: 'SidikKosong' };
    }

    return { Lolos: true };
}

/** Petunjuk berteks untuk karyawan selama kamera menyala. */
export function AmbilPetunjukWajah(penilaian: PenilaianWajah): string {
    if (penilaian.Lolos) {
        return 'Wajah terbaca. Tahan sebentar…';
    }

    switch (penilaian.Alasan) {
        case 'TidakAdaWajah':
            return 'Arahkan wajah ke dalam lingkaran, di tempat terang.';
        case 'BanyakWajah':
            return 'Pastikan hanya wajah Anda yang terlihat di kamera.';
        case 'BukanWajahAsli':
            return 'Wajah belum terbaca jelas. Lepas masker atau kacamata gelap, hadapkan wajah lurus.';
        case 'BelumKedip':
            return 'Kedipkan mata sekali.';
        case 'SidikKosong':
            return 'Tahan wajah tetap diam sebentar.';
    }
}

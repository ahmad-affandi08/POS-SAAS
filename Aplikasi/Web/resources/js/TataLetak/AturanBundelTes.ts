import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga penempatan bundle (D-20/D-21, D-28). File penjaga: kalau test ini gagal, perbaiki halamannya.
 *
 * Tiga entry point punya bundle & root view sendiri: `Aplikasi.tsx` (semua kecuali Pengelola & Situs),
 * `Pengelola.tsx`, dan `Situs.tsx` — masing-masing hanya me-resolve folder halamannya sendiri, dan perantaranya
 * membagikan prop yang berbeda (`BagikanDataSitus` memberi `Situs`, `BagikanDataInertia` tidak).
 *
 * Regresi yang dijaga: halaman legal publik memakai shell situs tetapi berada di `Halaman/Publik/`, jadi ia dimuat
 * bundle `Aplikasi` dan `TataLetakSitus` membaca `props.Situs` yang tidak pernah dibagikan — halamannya mati saat
 * dibuka, bukan gagal di test. Tata letak dan folder halaman harus cocok.
 */

const AKAR = 'resources/js/Halaman';

/** Semua berkas `.tsx` di bawah folder ini, rekursif, relatif ke `AKAR`. */
function BerkasHalaman(folder: string): string[] {
    return readdirSync(join(AKAR, folder), { withFileTypes: true }).flatMap((isi) => {
        const jalur = folder === '' ? isi.name : `${folder}/${isi.name}`;

        if (isi.isDirectory()) {
            return BerkasHalaman(jalur);
        }

        return jalur.endsWith('.tsx') && !/Tes\.tsx$/.test(isi.name) ? [jalur] : [];
    });
}

/** Halaman yang mengimpor tata letak tertentu. */
function PemakaiTataLetak(nama: string): string[] {
    return BerkasHalaman('').filter((jalur) =>
        readFileSync(join(AKAR, jalur), 'utf8').includes(`from '@/TataLetak/${nama}'`),
    );
}

describe('Penempatan tata letak & bundle halaman', () => {
    it('TataLetakSitus hanya dipakai halaman di Halaman/Situs (bundle Situs.tsx)', () => {
        const salahTempat = PemakaiTataLetak('TataLetakSitus').filter((jalur) => !jalur.startsWith('Situs/'));

        expect(salahTempat).toEqual([]);
    });

    it('TataLetakPengelola hanya dipakai halaman di Halaman/Pengelola (bundle Pengelola.tsx)', () => {
        const salahTempat = PemakaiTataLetak('TataLetakPengelola').filter((jalur) => !jalur.startsWith('Pengelola/'));

        expect(salahTempat).toEqual([]);
    });

    it('TataLetakAplikasi tidak dipakai halaman Pengelola atau Situs', () => {
        const salahTempat = PemakaiTataLetak('TataLetakAplikasi').filter(
            (jalur) => jalur.startsWith('Pengelola/') || jalur.startsWith('Situs/'),
        );

        expect(salahTempat).toEqual([]);
    });

    it('dokumen legal publik berada di bundle situs dan memakai shell situs', () => {
        // Alamatnya di domain pemasaran dan pengunjung sampai ke sini dari kaki situs, jadi harus ada jalan kembali.
        const isi = readFileSync(join(AKAR, 'Situs/DokumenLegal.tsx'), 'utf8');

        expect(isi).toContain("from '@/TataLetak/TataLetakSitus'");
        expect(isi).toContain("from '@/Komponen/Situs/TeksKaya'");
    });
});

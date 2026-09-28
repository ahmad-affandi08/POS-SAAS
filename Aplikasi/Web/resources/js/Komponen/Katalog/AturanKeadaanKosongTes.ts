import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga keadaan kosong (D-18 revisi pemilik produk). File penjaga: kalau test ini gagal, perbaiki halamannya.
 *
 * Regresi yang dijaga: sebelumnya ada sepuluh ilustrasi per subjek, jadi keadaan kosong terasa berbeda antar modul —
 * dan **19 daftar utama tidak kebagian ilustrasi sama sekali** karena subjeknya (pengguna, perangkat, satuan,
 * stasiun dapur, log audit, tenant, rilis, …) belum punya aset. Sekarang satu ilustrasi netral untuk semua daftar,
 * jadi tidak ada daftar yang tertinggal menunggu aset baru.
 */

const AKAR = 'resources/js/Halaman';
const FOLDER_ASET = 'resources/js/Aset/KeadaanKosong';

/**
 * Halaman rincian & dokumen. Tabel di sana bagian dari dokumennya (baris jurnal, riwayat poin, rincian penerimaan),
 * bukan daftar utama — ilustrasi sebesar itu di dalamnya justru membuat dokumen terlihat kosong padahal berisi.
 */
const HALAMAN_RINCIAN = [
    'Detail.tsx',
    'DetailGaji.tsx',
    'Form.tsx',
    'Ubah.tsx',
    'Buat.tsx',
    'Formulir.tsx',
    'Tampil.tsx',
    'Cetak.tsx',
];

/** Semua berkas halaman back-office (tenant & konsol), bukan berkas penjaga. */
function BerkasHalaman(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((isi) => {
        const jalur = join(folder, isi.name);

        if (isi.isDirectory()) {
            return BerkasHalaman(jalur);
        }

        return jalur.endsWith('.tsx') && !/Tes\.tsx$/.test(isi.name) ? [jalur] : [];
    });
}

/** Isi tiap prop `kosong={{ … }}` pada sebuah berkas. */
function KeadaanKosongDi(jalur: string): string[] {
    return [...readFileSync(jalur, 'utf8').matchAll(/kosong=\{\{([\s\S]*?)\}\}/g)].map((cocok) => cocok[1] ?? '');
}

describe('Keadaan kosong', () => {
    it('hanya ada satu aset ilustrasi', () => {
        // Sepuluh aset per subjek membuat keadaan kosong berbeda-beda antar modul, dan tiap daftar baru harus
        // menunggu ilustrasinya digambar dulu.
        expect(readdirSync(FOLDER_ASET)).toEqual(['Kosong.webp']);
    });

    it('setiap daftar utama menampilkan ilustrasi', () => {
        const pelanggar: string[] = [];

        for (const folder of ['Kelola', 'Pengelola']) {
            for (const jalur of BerkasHalaman(join(AKAR, folder))) {
                if (HALAMAN_RINCIAN.includes(jalur.split('/').pop() ?? '')) {
                    continue;
                }

                for (const isi of KeadaanKosongDi(jalur)) {
                    if (!isi.includes('ilustrasi')) {
                        pelanggar.push(jalur.replace(`${AKAR}/`, ''));
                    }
                }
            }
        }

        expect([...new Set(pelanggar)]).toEqual([]);
    });

    it('tabel di halaman rincian & dokumen tetap tanpa ilustrasi', () => {
        const pelanggar: string[] = [];

        for (const folder of ['Kelola', 'Pengelola']) {
            for (const jalur of BerkasHalaman(join(AKAR, folder))) {
                if (!HALAMAN_RINCIAN.includes(jalur.split('/').pop() ?? '')) {
                    continue;
                }

                for (const isi of KeadaanKosongDi(jalur)) {
                    if (isi.includes('ilustrasi')) {
                        pelanggar.push(jalur.replace(`${AKAR}/`, ''));
                    }
                }
            }
        }

        expect([...new Set(pelanggar)]).toEqual([]);
    });
});

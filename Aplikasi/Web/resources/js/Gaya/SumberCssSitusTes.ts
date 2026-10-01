import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * T1 (PRD v3.30): Gaya/Situs.css memakai `@import 'tailwindcss' source(none)` dan hanya memindai kode yang dipakai
 * situs pemasaran. Bila sebuah halaman situs mulai mengimpor komponen baru yang tidak tercakup `@source`, kelas
 * Tailwind-nya diam-diam tidak dibuat dan tampilan situs rusak di produksi. Test ini menelusuri seluruh impor dari
 * Situs.tsx (langsung maupun tidak) dan mewajibkan setiap berkas kode tercakup salah satu `@source` di Situs.css.
 */

const AKAR = join(process.cwd(), 'resources', 'js');
const GAYA = join(AKAR, 'Gaya');
const EKSTENSI = ['.tsx', '.ts'];

function SelesaikanImpor(dari: string, spesifikasi: string): string | null {
    let dasar: string;
    if (spesifikasi.startsWith('@/')) {
        dasar = join(AKAR, spesifikasi.slice(2));
    } else if (spesifikasi.startsWith('.')) {
        dasar = resolve(dirname(dari), spesifikasi);
    } else {
        return null; // paket npm
    }
    if (EKSTENSI.some((e) => dasar.endsWith(e)) && existsSync(dasar)) {
        return dasar;
    }
    for (const e of EKSTENSI) {
        if (existsSync(dasar + e)) {
            return dasar + e;
        }
    }
    for (const e of EKSTENSI) {
        const indeks = join(dasar, `index${e}`);
        if (existsSync(dasar) && statSync(dasar).isDirectory() && existsSync(indeks)) {
            return indeks;
        }
    }
    return null; // aset (.css, .png, .webp, ...)
}

function TelusuriImpor(awal: string): Set<string> {
    const terlihat = new Set<string>();
    const antre = [awal];
    while (antre.length > 0) {
        const berkas = antre.pop();
        if (berkas === undefined || terlihat.has(berkas)) {
            continue;
        }
        terlihat.add(berkas);
        const isi = readFileSync(berkas, 'utf8');
        const pola =
            /(?:import|export)\s[^'"]*?from\s*['"]([^'"]+)['"]|import\(\s*['"]([^'"]+)['"]\s*\)|import\s+['"]([^'"]+)['"]/g;
        for (const cocok of isi.matchAll(pola)) {
            const spesifikasi = cocok[1] ?? cocok[2] ?? cocok[3];
            const tujuan = spesifikasi ? SelesaikanImpor(berkas, spesifikasi) : null;
            if (tujuan) {
                antre.push(tujuan);
            }
        }
        // `import.meta.glob('./Halaman/Situs/**/*.tsx')`: semua berkas yang cocok ikut dimuat.
        for (const glob of isi.matchAll(/import\.meta\.glob[^(]*\(\s*['"]([^'"]+)['"]/g)) {
            const pola = glob[1] ?? '';
            const folder = resolve(dirname(berkas), pola.slice(0, pola.indexOf('*')));
            for (const b of DaftarBerkas(folder)) {
                antre.push(b);
            }
        }
    }
    return terlihat;
}

function DaftarBerkas(folder: string): string[] {
    return readdirSync(folder, { recursive: true, withFileTypes: true })
        .filter((d) => d.isFile() && EKSTENSI.some((e) => d.name.endsWith(e)) && !/Tes\.tsx?$/.test(d.name))
        .map((d) => join(d.parentPath, d.name));
}

/** `@source '../A/{x,y}.tsx'` → daftar pola jalur absolut (kurung kurawal diurai; folder = semua isinya). */
function AmbilSumber(): string[] {
    const css = readFileSync(join(GAYA, 'Situs.css'), 'utf8');
    const hasil: string[] = [];
    for (const cocok of css.matchAll(/^@source\s+'([^']+)';/gm)) {
        const pola = cocok[1] ?? '';
        const kurung = /\{([^}]+)\}/.exec(pola);
        const varian = kurung ? (kurung[1] ?? '').split(',').map((v) => pola.replace(kurung[0], v)) : [pola];
        hasil.push(...varian.map((v) => resolve(GAYA, v)));
    }
    return hasil;
}

describe('Gaya/Situs.css @source (T1)', () => {
    it('setiap berkas kode yang dimuat Situs.tsx tercakup salah satu @source', () => {
        const sumber = AmbilSumber();
        const CekTercakup = (berkas: string) => sumber.some((s) => berkas === s || berkas.startsWith(`${s}/`));
        const tidakTercakup = [...TelusuriImpor(join(AKAR, 'Situs.tsx'))]
            .filter((b) => !CekTercakup(b))
            .map((b) => relative(AKAR, b));

        expect(tidakTercakup, `Tambahkan @source di Gaya/Situs.css untuk:\n${tidakTercakup.join('\n')}`).toEqual([]);
    });

    it('situs tidak memindai seluruh kode (source(none)) dan memakai token yang sama dengan back-office', () => {
        const css = readFileSync(join(GAYA, 'Situs.css'), 'utf8');
        expect(css).toContain("@import 'tailwindcss' source(none);");
        expect(css).toContain("@import './Aplikasi.css';");
        expect(readFileSync(join(GAYA, 'Dasbor.css'), 'utf8')).toContain("@import './Aplikasi.css';");
    });
});

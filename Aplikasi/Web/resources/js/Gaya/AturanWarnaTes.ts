/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga "satu sumber warna" & "tanpa mode gelap" untuk aplikasi web (PRD §17.6.3, D-14).
 *
 * - Nilai warna (#hex, rgb(), hsl(), oklch(), ...) hanya boleh ditulis di Gaya/Aplikasi.css.
 * - Tidak ada varian `dark:`, `@custom-variant dark`, selektor `.dark`, `prefers-color-scheme`, atau next-themes.
 * - Tidak ada utilitas palet bawaan Tailwind (bg-white, text-red-500, ...): palet itu dimatikan di
 *   Aplikasi.css, jadi kelas seperti itu diam-diam tidak menghasilkan warna.
 * - Variabel shadcn di `:root` Aplikasi.css hanya merujuk token (var(--color-...)), tidak berisi nilai sendiri.
 *
 * Berkas ini sendiri dikecualikan dari pemindaian karena memuat contoh pola terlarang.
 */

const FILE_TOKEN = '/resources/js/Gaya/Aplikasi.css';
const FILE_INI = '/resources/js/Gaya/AturanWarnaTes.ts';

// Dibaca langsung dari disk (bukan import.meta.glob): Vitest mengosongkan isi file .css yang diimpor.
// Vitest berjalan dari akar Aplikasi/Web (lokasi vite.config.ts).
const AKAR_WEB = process.cwd();
const AKAR_JS = join(AKAR_WEB, 'resources', 'js');

function AmbilBerkasSumber(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((entri) => {
        const path = join(folder, entri.name);
        if (entri.isDirectory()) {
            return AmbilBerkasSumber(path);
        }
        return /\.(ts|tsx|css)$/.test(entri.name) ? [path] : [];
    });
}

const semuaBerkas: Record<string, string> = Object.fromEntries(
    AmbilBerkasSumber(AKAR_JS).map((path) => [
        '/' + relative(AKAR_WEB, path).split(sep).join('/'),
        readFileSync(path, 'utf8'),
    ]),
);

interface AturanTerlarang {
    Nama: string;
    Pola: RegExp;
    BolehDiFileToken: boolean;
}

const PALET_BAWAAN_TAILWIND =
    'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|mauve|olive|mist|taupe';
const AWALAN_UTILITAS_WARNA =
    'bg|text|border(?:-[xytrblse])?|ring|ring-offset|outline|fill|stroke|divide|from|via|to|shadow|inset-shadow|drop-shadow|text-shadow|accent|caret|decoration|placeholder';

const daftarAturan: AturanTerlarang[] = [
    { Nama: 'varian dark:', Pola: /(?<![\w-])dark:/, BolehDiFileToken: false },
    { Nama: 'varian kustom dark', Pola: /@(?:custom-)?variant\s+dark\b/, BolehDiFileToken: false },
    { Nama: 'selektor .dark', Pola: /(?<![\w-])\.dark(?![\w-])/, BolehDiFileToken: false },
    { Nama: 'prefers-color-scheme', Pola: /prefers-color-scheme/, BolehDiFileToken: false },
    { Nama: 'next-themes', Pola: /next-themes/, BolehDiFileToken: false },
    {
        Nama: 'literal warna hex',
        Pola: /(?<![&\w.])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])/,
        BolehDiFileToken: true,
    },
    {
        Nama: 'fungsi warna (rgb/hsl/oklch/...)',
        Pola: /(?<![\w-])(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\(/,
        BolehDiFileToken: true,
    },
    {
        Nama: 'utilitas palet bawaan Tailwind',
        Pola: new RegExp(
            `(?<![\\w-])(?:${AWALAN_UTILITAS_WARNA})-(?:white|black|(?:${PALET_BAWAAN_TAILWIND})-\\d{2,3})(?:\\/\\d+)?(?![\\w-])`,
        ),
        BolehDiFileToken: false,
    },
];

function CariPelanggaran(path: string, isi: string): string[] {
    const hasil: string[] = [];
    isi.split('\n').forEach((baris, indeks) => {
        for (const aturan of daftarAturan) {
            if (path === FILE_TOKEN && aturan.BolehDiFileToken) {
                continue;
            }
            if (aturan.Pola.test(baris)) {
                hasil.push(`${path}:${indeks + 1} [${aturan.Nama}] ${baris.trim()}`);
            }
        }
    });
    return hasil;
}

describe('aturan warna web (satu sumber warna, tanpa mode gelap)', () => {
    it('pola penjaga mengenali pelanggaran dan membiarkan pemakaian token', () => {
        const Melanggar = (baris: string, path = '/resources/js/Contoh.tsx') => CariPelanggaran(path, baris).length > 0;

        expect(Melanggar('className="bg-latar dark:bg-teks-utama"')).toBe(true);
        expect(Melanggar('"dark:*:data-[slot=x]:bg-muted"')).toBe(true);
        expect(Melanggar('@custom-variant dark (&:is(.dark *));')).toBe(true);
        expect(Melanggar('.dark { --background: var(--color-latar); }')).toBe(true);
        expect(Melanggar('@media (prefers-color-scheme: dark) {}')).toBe(true);
        expect(Melanggar("import { useTheme } from 'next-themes';")).toBe(true);
        expect(Melanggar('style={{ color: "#0b6468" }}')).toBe(true);
        expect(Melanggar('className="bg-[#fafaf7]"')).toBe(true);
        expect(Melanggar('className="text-[rgb(0_0_0)]"')).toBe(true);
        expect(Melanggar('fill: oklch(0.5 0.1 200);')).toBe(true);
        expect(Melanggar('className="bg-white text-black"')).toBe(true);
        expect(Melanggar('className="text-red-500 hover:bg-slate-100/50"')).toBe(true);

        expect(Melanggar('className="bg-permukaan text-teks-utama border-garis ring-brand"')).toBe(false);
        expect(Melanggar('className="bg-primary text-primary-foreground bg-tirai shadow-sm"')).toBe(false);
        expect(Melanggar('className="hover:bg-[color-mix(in_oklch,var(--muted),var(--foreground)_5%)]"')).toBe(false);
        expect(Melanggar('<a href="#konten">Lewati ke konten</a>')).toBe(false);
        expect(Melanggar('this.#abc = 1;')).toBe(false);
        expect(Melanggar('--color-brand: #0b6468;', FILE_TOKEN)).toBe(false);
        expect(Melanggar('.dark { --color-brand: #0b6468; }', FILE_TOKEN)).toBe(true);
    });

    it('memindai seluruh resources/js termasuk file token dan komponen shadcn', () => {
        const daftarPath = Object.keys(semuaBerkas);
        expect(daftarPath).toContain(FILE_TOKEN);
        expect(daftarPath).toContain('/resources/js/Komponen/Ui/button.tsx');
        expect(daftarPath).toContain('/resources/js/Komponen/Ui/sonner.tsx');
        expect(daftarPath.length).toBeGreaterThan(100);
    });

    it('tidak ada warna lepas di luar Aplikasi.css dan tidak ada mode gelap', () => {
        const pelanggaran = Object.entries(semuaBerkas)
            .filter(([path]) => path !== FILE_INI)
            .flatMap(([path, isi]) => CariPelanggaran(path, isi));

        expect(pelanggaran, `Pakai token dari Gaya/Aplikasi.css:\n${pelanggaran.join('\n')}`).toEqual([]);
    });

    it('variabel shadcn di :root Aplikasi.css hanya merujuk token', () => {
        const css = semuaBerkas[FILE_TOKEN] ?? '';
        const blokRoot = /(?:^|\n):root\s*\{([^}]*)\}/.exec(css)?.[1];
        expect(blokRoot, 'Blok :root pemetaan shadcn tidak ditemukan di Aplikasi.css').toBeDefined();

        const deklarasi = [...(blokRoot ?? '').matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)].map((cocok) => ({
            Nama: cocok[1] ?? '',
            Nilai: (cocok[2] ?? '').trim(),
        }));
        const wajib = ['--background', '--foreground', '--primary', '--destructive', '--border', '--ring', '--radius'];
        expect(deklarasi.map((d) => d.Nama)).toEqual(expect.arrayContaining(wajib));

        const bukanRujukan = deklarasi.filter((d) => !/^var\(--(?:color|radius)-[\w-]+\)$/.test(d.Nilai));
        expect(bukanRujukan, 'Variabel shadcn harus berupa var(--color-...) atau var(--radius-...)').toEqual([]);
    });
});

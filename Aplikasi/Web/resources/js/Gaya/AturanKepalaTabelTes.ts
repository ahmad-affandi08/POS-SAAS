/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga "teks kepala tabel tebal, diatur dari komponen" (PRD v1.78).
 * - TableHead (Komponen/Ui/table.tsx) memakai font-bold; TabelData tidak menimpanya.
 * - Halaman & komponen lain tidak menimpa ketebalan TableHead (font-semibold/font-medium) langsung di tag-nya.
 */

// Vitest berjalan dari akar Aplikasi/Web (lokasi vite.config.ts).
const AKAR_JS = join(process.cwd(), 'resources', 'js');

function AmbilBerkasTsx(folder: string): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((entri) => {
        const path = join(folder, entri.name);
        if (entri.isDirectory()) {
            return AmbilBerkasTsx(path);
        }

        return entri.name.endsWith('.tsx') && !entri.name.endsWith('Tes.tsx') ? [path] : [];
    });
}

describe('Kepala tabel tebal', () => {
    it('TableHead memakai font-bold dan kelas kepala TabelData tidak menimpanya', () => {
        const tabel = readFileSync(join(AKAR_JS, 'Komponen', 'Ui', 'table.tsx'), 'utf8');
        const tabelData = readFileSync(join(AKAR_JS, 'Komponen', 'TabelData', 'TabelData.tsx'), 'utf8');

        expect(/function TableHead[\s\S]*?font-bold/.test(tabel)).toBe(true);
        expect(/const kelasKepala = '[^']*font-(semibold|medium|normal)/.test(tabelData)).toBe(false);
    });

    it('tidak ada tag TableHead di luar Komponen/Ui yang menimpa ketebalan dengan font-semibold/font-medium', () => {
        const pelanggar = [...AmbilBerkasTsx(join(AKAR_JS, 'Halaman')), ...AmbilBerkasTsx(join(AKAR_JS, 'Komponen'))]
            .filter((path) => !path.includes(`${join('Komponen', 'Ui')}`))
            .filter((path) => /<TableHead\b[^>]*font-(semibold|medium)\b/.test(readFileSync(path, 'utf8')))
            .map((path) => relative(AKAR_JS, path));

        expect(pelanggar).toEqual([]);
    });
});

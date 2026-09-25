/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga "semua yang bisa diklik memakai kursor tangan" (PRD §17.6, v1.76).
 * - Aplikasi.css punya aturan dasar cursor: pointer untuk tombol, tautan, dan peran ARIA yang bisa diklik.
 * - Komponen shadcn/ui tidak boleh memaksa `cursor-default` pada item menu/opsi yang bisa diklik.
 */

// Vitest berjalan dari akar Aplikasi/Web (lokasi vite.config.ts).
const AKAR_JS = join(process.cwd(), 'resources', 'js');

describe('Kursor elemen yang bisa diklik', () => {
    it('Aplikasi.css memberi cursor: pointer untuk tombol, tautan, dan peran ARIA yang bisa diklik', () => {
        const css = readFileSync(join(AKAR_JS, 'Gaya', 'Aplikasi.css'), 'utf8');
        const aturan =
            /:is\(([^{]*)\):not\(:disabled, \[aria-disabled='true'\], \[data-disabled\]\) \{\s*cursor: pointer;/.exec(
                css,
            );

        expect(aturan).not.toBeNull();
        for (const selektor of [
            'button',
            'a[href]',
            "[role='button']",
            "[role='menuitem']",
            "[role='option']",
            "[role='tab']",
        ]) {
            expect(aturan?.[1]).toContain(selektor);
        }
    });

    it('komponen Ui tidak memakai cursor-default untuk item yang bisa diklik', () => {
        const folderUi = join(AKAR_JS, 'Komponen', 'Ui');
        const pelanggar = readdirSync(folderUi)
            .filter((nama) => nama.endsWith('.tsx'))
            .filter((nama) => /\bcursor-default\b/.test(readFileSync(join(folderUi, nama), 'utf8')));

        expect(pelanggar).toEqual([]);
    });
});

import { describe, expect, it } from 'vitest';

import { FormatDurasi, FormatUkuranBerkas } from './FormatUkuran';

describe('FormatUkuranBerkas', () => {
    it('memakai satuan biner dan koma desimal Indonesia', () => {
        expect(FormatUkuranBerkas(512)).toBe('512 B');
        expect(FormatUkuranBerkas(52_428_800)).toBe('50 MB');
        expect(FormatUkuranBerkas(1_610_612_736)).toBe('1,5 GB');
        expect(FormatUkuranBerkas(null)).toBe('—');
    });
});

describe('FormatDurasi', () => {
    it('meringkas detik menjadi menit, jam, atau hari', () => {
        expect(FormatDurasi(45)).toBe('45 detik');
        expect(FormatDurasi(360)).toBe('6 menit');
        expect(FormatDurasi(4 * 3600)).toBe('4 jam');
        expect(FormatDurasi(3 * 86400)).toBe('3 hari');
        expect(FormatDurasi(null)).toBe('—');
    });
});

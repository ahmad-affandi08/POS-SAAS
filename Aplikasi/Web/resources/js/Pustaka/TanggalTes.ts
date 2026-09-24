import { describe, expect, it } from 'vitest';

import {
    BuatPresetTanggal,
    CekDiLuarBatas,
    FormatTanggalIsian,
    GabungRentang,
    PecahRentang,
    TulisTanggal,
    UraiTanggal,
    UraiTeksJam,
    UraiTeksTanggal,
} from './Tanggal';

describe('Pustaka/Tanggal', () => {
    it('mengurai & menulis TTTT-BB-HH tanpa geser zona waktu, menolak tanggal yang tidak ada', () => {
        expect(TulisTanggal(UraiTanggal('2026-01-31') ?? new Date(0))).toBe('2026-01-31');
        expect(UraiTanggal('2026-02-30')).toBeUndefined();
        expect(UraiTanggal('2026-')).toBeUndefined();
        expect(FormatTanggalIsian('2026-09-04')).toBe('04/09/2026');
        expect(FormatTanggalIsian('')).toBe('');
    });

    it('menerima ketikan HH/BB/TTTT dan variannya, juga ISO', () => {
        expect(UraiTeksTanggal('24/09/2026')).toBe('2026-09-24');
        expect(UraiTeksTanggal('4-9-2026')).toBe('2026-09-04');
        expect(UraiTeksTanggal('04.09.2026')).toBe('2026-09-04');
        expect(UraiTeksTanggal(' 2026-09-24 ')).toBe('2026-09-24');
        expect(UraiTeksTanggal('29/02/2026')).toBeUndefined();
        expect(UraiTeksTanggal('24/09/26')).toBeUndefined();
        expect(UraiTeksTanggal('')).toBeUndefined();
    });

    it('jam 24 jam dengan titik atau titik dua', () => {
        expect(UraiTeksJam('9:05')).toBe('09:05');
        expect(UraiTeksJam('23.59')).toBe('23:59');
        expect(UraiTeksJam('24:00')).toBeUndefined();
        expect(UraiTeksJam('9')).toBeUndefined();
    });

    it('batas min/max dan rentang', () => {
        expect(CekDiLuarBatas('2026-09-25', undefined, '2026-09-24')).toBe(true);
        expect(CekDiLuarBatas('2026-09-24', '2026-09-24', '2026-09-24')).toBe(false);
        expect(PecahRentang('2026-09-01..')).toEqual(['2026-09-01', '']);
        expect(GabungRentang('', '')).toBe('');
        expect(GabungRentang('', '2026-09-30')).toBe('..2026-09-30');
    });

    it('preset rentang dihitung dari hari ini (lintas bulan & tahun kabisat)', () => {
        const preset = new Map(BuatPresetTanggal(new Date(2028, 2, 1)).map((p) => [p.label, p.nilai]));

        expect(preset.get('Kemarin')).toBe('2028-02-29..2028-02-29');
        expect(preset.get('7 hari terakhir')).toBe('2028-02-24..2028-03-01');
        expect(preset.get('Bulan lalu')).toBe('2028-02-01..2028-02-29');
        expect(preset.get('Tahun ini')).toBe('2028-01-01..2028-12-31');
    });
});

import { describe, expect, it } from 'vitest';

import { FormatTanggal, FormatTanggalWaktu } from './FormatWaktu';

describe('FormatTanggalWaktu (PRD §17.6.7)', () => {
    it('menampilkan waktu UTC dari API dalam WIB', () => {
        expect(FormatTanggalWaktu('2026-09-23T03:05:00+00:00')).toBe('23 Sep 2026, 10.05 WIB');
    });

    it('menampilkan tanda pisah untuk nilai kosong', () => {
        expect(FormatTanggalWaktu(null)).toBe('—');
    });

    it('menolak teks yang bukan waktu', () => {
        expect(() => FormatTanggalWaktu('kemarin')).toThrow();
    });
});

describe('FormatTanggal', () => {
    it('menampilkan tanggal kalender gaya Indonesia', () => {
        expect(FormatTanggal('2027-01-01')).toBe('1 Jan 2027');
        expect(FormatTanggal(null)).toBe('—');
    });

    it('menerima waktu ISO-8601 lengkap dan menampilkan tanggal WIB', () => {
        expect(FormatTanggal('2026-09-26T18:17:54+00:00')).toBe('27 Sep 2026');
        expect(FormatTanggal('2026-09-26T10:00:00Z')).toBe('26 Sep 2026');
        expect(FormatTanggal('2026-09-26T10:00:00.123456Z')).toBe('26 Sep 2026');
    });

    it('menolak format selain TTTT-BB-HH atau waktu ISO-8601', () => {
        expect(() => FormatTanggal('01-01-2027')).toThrow();
        expect(() => FormatTanggal('2026-02-30')).not.toThrow();
        expect(() => FormatTanggal('2026-09-26 18:17:54')).toThrow();
        expect(() => FormatTanggal('kemarin')).toThrow();
    });
});

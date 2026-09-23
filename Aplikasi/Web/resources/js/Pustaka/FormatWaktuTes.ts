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

    it('menolak format selain TTTT-BB-HH', () => {
        expect(() => FormatTanggal('01-01-2027')).toThrow();
    });
});

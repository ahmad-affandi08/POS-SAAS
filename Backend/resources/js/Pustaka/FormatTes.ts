import { describe, expect, it } from 'vitest';

import { FormatRupiah } from './Format';

describe('FormatRupiah (PRD §17.6.7)', () => {
    it('memformat string desimal dari API gaya Indonesia', () => {
        expect(FormatRupiah('1250000.00')).toBe('Rp 1.250.000');
        expect(FormatRupiah('63500')).toBe('Rp 63.500');
        expect(FormatRupiah('1234.5')).toBe('Rp 1.234,50');
        expect(FormatRupiah('0.00')).toBe('Rp 0');
    });

    it('memberi tanda minus tipografis untuk nilai negatif', () => {
        expect(FormatRupiah('-6000.00')).toBe('−Rp 6.000');
        expect(FormatRupiah('-0.00')).toBe('Rp 0');
    });

    it('tetap presisi untuk angka di luar batas aman float JavaScript', () => {
        expect(FormatRupiah('90071992547409931.99')).toBe('Rp 90.071.992.547.409.931,99');
    });

    it('menolak masukan yang bukan string desimal uang', () => {
        expect(() => FormatRupiah('12,50')).toThrow();
        expect(() => FormatRupiah('1.005')).toThrow();
    });
});

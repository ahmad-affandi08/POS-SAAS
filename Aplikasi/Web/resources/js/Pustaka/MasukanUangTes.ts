import { describe, expect, it } from 'vitest';

import {
    CekMasukanUangValid,
    FormatMasukanPersen,
    FormatMasukanUang,
    HitungDigitSebelumKursor,
    HitungPosisiKursor,
    NormalisasiMasukanPersen,
    NormalisasiMasukanUang,
} from '@/Pustaka/MasukanUang';

describe('NormalisasiMasukanUang (F-01: Harga dikirim sebagai string desimal polos)', () => {
    it('membuang "Rp", spasi, dan titik ribuan', () => {
        expect(NormalisasiMasukanUang('Rp 1.250.000')).toBe('1250000');
        expect(NormalisasiMasukanUang('Rp 15.000')).toBe('15000');
        expect(NormalisasiMasukanUang('rp15000')).toBe('15000');
        expect(NormalisasiMasukanUang(' 22.000 ')).toBe('22000');
        expect(NormalisasiMasukanUang('Rp 1.250.000.000')).toBe('1250000000');
    });

    it('teks kosong tetap kosong', () => {
        expect(NormalisasiMasukanUang('')).toBe('');
        expect(NormalisasiMasukanUang('Rp ')).toBe('');
    });

    it('membuang nol di depan dan sen nol, mempertahankan sen dua digit', () => {
        expect(NormalisasiMasukanUang('007')).toBe('7');
        expect(NormalisasiMasukanUang('0')).toBe('0');
        expect(NormalisasiMasukanUang('15.000,00')).toBe('15000');
        expect(NormalisasiMasukanUang('1.250.000,50')).toBe('1250000.50');
    });

    it('menolak koma desimal satu digit, huruf, tanda minus, dan lebih dari 16 digit', () => {
        for (const teks of ['12,5', '12a', '-5000', '1,2,3', ',50', '12345678901234567']) {
            expect(CekMasukanUangValid(teks)).toBe(false);
            expect(() => NormalisasiMasukanUang(teks)).toThrow();
        }
        expect(CekMasukanUangValid('Rp 1.250.000')).toBe(true);
        expect(CekMasukanUangValid('')).toBe(true);
    });
});

describe('FormatMasukanUang (tampilan di dalam BidangUang, tanpa float)', () => {
    it('menyisipkan titik ribuan dan membuang sen nol', () => {
        expect(FormatMasukanUang('1250000000')).toBe('1.250.000.000');
        expect(FormatMasukanUang('22000.00')).toBe('22.000');
        expect(FormatMasukanUang('22000.5')).toBe('22.000,50');
        expect(FormatMasukanUang('999')).toBe('999');
        expect(FormatMasukanUang('')).toBe('');
    });

    it('bolak-balik dengan normalisasi', () => {
        expect(NormalisasiMasukanUang(FormatMasukanUang('9999999999999999'))).toBe('9999999999999999');
    });
});

describe('Posisi kursor setelah format ulang', () => {
    it('kursor tetap setelah digit yang sama walau titik ribuan bertambah', () => {
        // Pengguna mengetik "5" di akhir "1.500" → teks mentah "1.5005", 5 digit sebelum kursor.
        const digit = HitungDigitSebelumKursor('1.5005', 6);
        expect(digit).toBe(5);
        expect(HitungPosisiKursor('15.005', digit)).toBe(6);
        expect(HitungPosisiKursor('15.005', 2)).toBe(2);
        expect(HitungPosisiKursor('15.005', 3)).toBe(4);
        expect(HitungPosisiKursor('15.005', 0)).toBe(0);
    });
});

describe('Masukan persen service charge', () => {
    it('koma desimal diubah ke titik untuk server dan sebaliknya untuk tampilan', () => {
        expect(NormalisasiMasukanPersen('7,5')).toBe('7.5');
        expect(NormalisasiMasukanPersen('10 %')).toBe('10');
        expect(FormatMasukanPersen('5.00')).toBe('5,00');
    });
});

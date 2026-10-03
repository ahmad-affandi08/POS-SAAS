import { describe, expect, it } from 'vitest';

import {
    AmbilPetunjukWajah,
    KeAkurasiMeter,
    KeSidikWajah,
    KeTeksKoordinat,
    NilaiWajah,
} from '@/Fitur/Absensi/SidikWajah';

/*
 * F-18 bagian 4 (D-37): sidik wajah dikirim sebagai bilangan bulat (deskriptor × 10.000, dibatasi ±100.000) dan
 * penilaian wajah di HP mensyaratkan satu wajah asli & hidup yang sudah berkedip.
 */
describe('Sidik wajah absensi web', () => {
    it('deskriptor dikali 10.000, dibulatkan, dibatasi, dan nilai tak hingga jadi 0', () => {
        expect(KeSidikWajah([0.12345, -0.5, 20, Number.NaN])).toEqual([1235, -5000, 100000, 0]);
    });

    it('koordinat 7 angka desimal; akurasi dibulatkan ke atas', () => {
        expect(KeTeksKoordinat(-7.556)).toBe('-7.5560000');
        expect(KeAkurasiMeter(12.2)).toBe(13);
        expect(KeAkurasiMeter(-1)).toBe(0);
    });

    it('penilaian wajah: kosong, ganda, palsu, belum kedip, tanpa sidik, lalu lolos', () => {
        const asli = { real: 0.9, live: 0.8, embedding: [0.1, 0.2] };

        expect(NilaiWajah([], true)).toEqual({ Lolos: false, Alasan: 'TidakAdaWajah' });
        expect(NilaiWajah([asli, asli], true)).toEqual({ Lolos: false, Alasan: 'BanyakWajah' });
        expect(NilaiWajah([{ ...asli, real: 0.2 }], true)).toEqual({ Lolos: false, Alasan: 'BukanWajahAsli' });
        expect(NilaiWajah([{ real: 0.9, embedding: [0.1] }], true)).toEqual({ Lolos: false, Alasan: 'BukanWajahAsli' });
        expect(NilaiWajah([asli], false)).toEqual({ Lolos: false, Alasan: 'BelumKedip' });
        expect(NilaiWajah([{ ...asli, embedding: [] }], true)).toEqual({ Lolos: false, Alasan: 'SidikKosong' });
        expect(NilaiWajah([asli], true)).toEqual({ Lolos: true });
        expect(AmbilPetunjukWajah({ Lolos: false, Alasan: 'BelumKedip' })).toBe('Kedipkan mata sekali.');
    });
});

import { describe, expect, it } from 'vitest';

import { BuatUlid, CekUlid } from '@/Pustaka/Ulid';

describe('BuatUlid (F-17 Uuid dari peramban)', () => {
    it('26 karakter Crockford base32, awalan waktu berurutan, acak berbeda', () => {
        const a = BuatUlid(1_700_000_000_000);
        const b = BuatUlid(1_700_000_000_001);

        expect(CekUlid(a)).toBe(true);
        expect(a).toHaveLength(26);
        expect(a.slice(0, 10) < b.slice(0, 10)).toBe(true);
        expect(BuatUlid()).not.toBe(BuatUlid());
        expect(CekUlid('01ARZ3NDEKTSV4RRFFQ69G5FAV')).toBe(true);
        expect(CekUlid('01ARZ3NDEKTSV4RRFFQ69G5FAU')).toBe(false);
    });
});

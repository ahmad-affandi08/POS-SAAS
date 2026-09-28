import { describe, expect, it } from 'vitest';

import { AmbilPesanGalat, AmbilTokenXsrf } from './PermintaanJson';

describe('AmbilTokenXsrf', () => {
    it('mengambil token walau cookie lain mendahuluinya', () => {
        expect(AmbilTokenXsrf('payou_session=abc; XSRF-TOKEN=tok123; lain=1')).toBe('tok123');
    });

    it('mengambil token saat berada di awal', () => {
        expect(AmbilTokenXsrf('XSRF-TOKEN=tok123')).toBe('tok123');
    });

    it('mengurai persen-encoding, karena Laravel meng-URL-encode nilai cookienya', () => {
        expect(AmbilTokenXsrf('XSRF-TOKEN=a%3Db%3D')).toBe('a=b=');
    });

    it('tidak tertipu cookie yang namanya berakhiran sama', () => {
        expect(AmbilTokenXsrf('LAIN-XSRF-TOKEN=salah')).toBe('');
    });

    it('kosong bila cookienya tidak ada', () => {
        expect(AmbilTokenXsrf('')).toBe('');
    });
});

describe('AmbilPesanGalat', () => {
    it('mengambil pesan dari format galat API', () => {
        expect(AmbilPesanGalat({ Galat: { Kode: 'GerbangMenolak', Pesan: 'Gerbang menolak.' } })).toBe(
            'Gerbang menolak.',
        );
    });

    it('null untuk bentuk lain, termasuk null dan pesan kosong', () => {
        expect(AmbilPesanGalat(null)).toBeNull();
        expect(AmbilPesanGalat({ message: 'Server Error' })).toBeNull();
        expect(AmbilPesanGalat({ Galat: { Pesan: '' } })).toBeNull();
    });
});

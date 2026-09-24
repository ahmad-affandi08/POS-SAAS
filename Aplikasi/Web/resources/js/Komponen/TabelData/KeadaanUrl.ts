import { UKURAN_HALAMAN, type KeadaanTabel, type UrutKolom } from './Tipe';

/** Urut `-Kolom,Kolom2` ↔ daftar urut TanStack. */
export function BacaUrut(nilai: string | null): UrutKolom[] {
    if (!nilai) {
        return [];
    }

    return nilai
        .split(',')
        .map((bagian) => bagian.trim())
        .filter((bagian) => bagian !== '' && bagian !== '-')
        .slice(0, 3)
        .map((bagian) => ({ id: bagian.replace(/^-/, ''), desc: bagian.startsWith('-') }));
}

export function TulisUrut(urut: UrutKolom[]): string {
    return urut.map((u) => `${u.desc ? '-' : ''}${u.id}`).join(',');
}

/** Membaca keadaan tabel dari query string; nilai tidak sah jatuh ke bawaan. */
export function BacaKeadaanDariUrl(query: string, urutBawaan: UrutKolom[]): KeadaanTabel {
    const parameter = new URLSearchParams(query);
    const halaman = Number.parseInt(parameter.get('halaman') ?? '', 10);
    const perHalaman = Number.parseInt(parameter.get('perHalaman') ?? '', 10);
    const saring: Record<string, string> = {};

    parameter.forEach((nilai, kunci) => {
        const cocok = /^saring\[([A-Za-z0-9]+)\]$/.exec(kunci);

        if (cocok?.[1] && nilai.trim() !== '') {
            saring[cocok[1]] = nilai;
        }
    });

    const urut = BacaUrut(parameter.get('urut'));

    return {
        cari: parameter.get('cari') ?? '',
        urut: urut.length > 0 ? urut : urutBawaan,
        halaman: Number.isInteger(halaman) && halaman > 0 ? halaman : 1,
        perHalaman: (UKURAN_HALAMAN as readonly number[]).includes(perHalaman) ? perHalaman : UKURAN_HALAMAN[0],
        saring,
    };
}

/**
 * Menulis keadaan ke query string. Nilai bawaan tidak ditulis agar URL tetap pendek. Parameter lain milik halaman
 * (di luar kunci tabel) dipertahankan dari `queryAsal`.
 */
export function TulisKeadaanKeUrl(keadaan: KeadaanTabel, urutBawaan: UrutKolom[], queryAsal = ''): string {
    const parameter = new URLSearchParams(queryAsal);

    for (const kunci of [...parameter.keys()]) {
        if (['cari', 'urut', 'halaman', 'perHalaman'].includes(kunci) || kunci.startsWith('saring[')) {
            parameter.delete(kunci);
        }
    }

    if (keadaan.cari.trim() !== '') {
        parameter.set('cari', keadaan.cari.trim());
    }

    const urut = TulisUrut(keadaan.urut);

    if (urut !== TulisUrut(urutBawaan)) {
        parameter.set('urut', urut);
    }

    if (keadaan.halaman > 1) {
        parameter.set('halaman', String(keadaan.halaman));
    }

    if (keadaan.perHalaman !== UKURAN_HALAMAN[0]) {
        parameter.set('perHalaman', String(keadaan.perHalaman));
    }

    for (const [kunci, nilai] of Object.entries(keadaan.saring).sort(([a], [b]) => a.localeCompare(b))) {
        if (nilai.trim() !== '') {
            parameter.set(`saring[${kunci}]`, nilai);
        }
    }

    return parameter.toString();
}

/** Jumlah saring aktif + pencarian (untuk lencana tombol "Saring"). */
export function HitungSaringAktif(keadaan: KeadaanTabel): number {
    return Object.values(keadaan.saring).filter((nilai) => nilai.trim() !== '').length;
}

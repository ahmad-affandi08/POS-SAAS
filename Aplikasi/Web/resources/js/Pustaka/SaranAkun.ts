import { useState } from 'react';

/**
 * Audit kemudahan pakai #15: kategori/transaksi kas memakai bahasa sehari-hari. Nama akun template sudah ramah
 * ("Beban Sewa, Listrik, Air, Internet"), jadi kode akun disembunyikan kecuali pengguna menyalakan "Tampilkan kode
 * akun" (mode akuntan, diingat per peramban), dan akun lawan disarankan dari kata pada nama kategori/keterangan.
 */
type AkunBernama = { Kode: string; Nama: string };

/** Pasangan kata pada nama kategori → kata pada nama akun template. Urutan = prioritas. */
const kataKunci: [RegExp, RegExp][] = [
    [/gaji|upah|komisi|lembur|bonus|thr|honor/i, /gaji/i],
    [/sewa|kontrak|listrik|pln|token|air|pdam|internet|wifi|pulsa|telepon|telpon/i, /sewa|listrik/i],
    [/promosi|iklan|brosur|spanduk|endorse|ads/i, /promosi/i],
    [/admin bank|biaya transfer|mdr|biaya bayar|potongan qris/i, /biaya pembayaran/i],
    [/setoran modal|tambah modal|modal pemilik/i, /modal/i],
    [/receh|kembalian|uang kecil/i, /lain/i],
];

/** Akun yang paling cocok untuk [nama]; cadangan = akun "lain-lain", lalu satu-satunya akun. */
export function SaranAkun<T extends AkunBernama>(nama: string, opsi: T[]): T | null {
    const teks = nama.trim();

    if (teks !== '') {
        for (const [pola, akun] of kataKunci) {
            if (pola.test(teks)) {
                const cocok = opsi.find((o) => akun.test(o.Nama));

                if (cocok) {
                    return cocok;
                }
            }
        }
    }

    return opsi.find((o) => /lain/i.test(o.Nama)) ?? (opsi.length === 1 ? (opsi[0] ?? null) : null);
}

/** Label pilihan akun: nama saja, atau "kode nama" di mode akuntan. */
export function LabelAkun(akun: AkunBernama, tampilKode: boolean): string {
    return tampilKode ? `${akun.Kode} ${akun.Nama}` : akun.Nama;
}

const KUNCI_TAMPIL_KODE = 'Akuntansi.TampilKodeAkun';

function BacaTampilKode(): boolean {
    try {
        return window.localStorage.getItem(KUNCI_TAMPIL_KODE) === '1';
    } catch {
        return false;
    }
}

/** Sakelar "Tampilkan kode akun" yang diingat per peramban (kenyamanan tampilan, bukan data). */
export function useTampilKodeAkun(): [boolean, (nilai: boolean) => void] {
    const [tampil, AturTampil] = useState(BacaTampilKode);

    const Ubah = (nilai: boolean) => {
        AturTampil(nilai);

        try {
            window.localStorage.setItem(KUNCI_TAMPIL_KODE, nilai ? '1' : '0');
        } catch {
            // Penyimpanan peramban diblokir: pilihan berlaku untuk halaman ini saja.
        }
    };

    return [tampil, Ubah];
}

/**
 * ULID (26 karakter Crockford base32: 10 karakter waktu ms + 16 karakter acak) untuk Uuid buatan peramban, misal
 * idempotensi kiriman pesanan QR meja (F-17). Acak dari `crypto.getRandomValues`.
 */
const ABJAD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

export function BuatUlid(waktu: number = Date.now()): string {
    let bagianWaktu = '';
    let sisa = Math.floor(waktu);

    for (let i = 0; i < 10; i++) {
        bagianWaktu = ABJAD.charAt(sisa % 32) + bagianWaktu;
        sisa = Math.floor(sisa / 32);
    }

    const acak = new Uint8Array(16);
    crypto.getRandomValues(acak);
    const bagianAcak = Array.from(acak, (bita) => ABJAD.charAt(bita % 32)).join('');

    return bagianWaktu + bagianAcak;
}

export function CekUlid(teks: string): boolean {
    return /^[0-9A-HJKMNP-TV-Z]{26}$/.test(teks);
}

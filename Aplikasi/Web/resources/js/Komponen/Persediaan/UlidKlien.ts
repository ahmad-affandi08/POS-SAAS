/**
 * ULID buatan peramban untuk kunci idempotensi dokumen stok awal baru (DesainF05a C.6: "Create is idempotent by
 * client Uuid"; `Uuid` POST berpola ULID). Dibuat sekali per form, jadi kirim ulang setelah koneksi putus tidak
 * menggandakan draf.
 */
const alfabetCrockford = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

function TulisBasis32(nilai: bigint, panjang: number): string {
    let hasil = '';
    let sisa = nilai;

    for (let i = 0; i < panjang; i += 1) {
        hasil = `${alfabetCrockford[Number(sisa % 32n)] ?? '0'}${hasil}`;
        sisa /= 32n;
    }

    return hasil;
}

/** ULID 26 karakter: 48 bit waktu milidetik + 80 bit acak kriptografis. */
export function BuatUlid(waktuMs: number = Date.now()): string {
    const acak = new Uint8Array(10);
    crypto.getRandomValues(acak);
    const nilaiAcak = acak.reduce((jumlah, bita) => (jumlah << 8n) | BigInt(bita), 0n);

    return `${TulisBasis32(BigInt(waktuMs), 10)}${TulisBasis32(nilaiAcak, 16)}`;
}

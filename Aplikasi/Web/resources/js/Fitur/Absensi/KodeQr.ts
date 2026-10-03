/**
 * F-18 bagian 4 (D-37): kode layar QR absensi outlet. Isi QR di layar = 6 angka; karyawan memindainya atau
 * mengetiknya. Spasi/pemisah yang ikut terketik dibuang.
 */
export function NormalkanKodeQr(teks: string): string {
    return teks.replace(/\D/g, '').slice(0, 6);
}

export function CekKodeQrLengkap(kode: string): boolean {
    return /^\d{6}$/.test(kode);
}

type PendeteksiBarcode = { detect: (sumber: CanvasImageSource) => Promise<{ rawValue: string }[]> };
type KelasPendeteksiBarcode = new (opsi: { formats: string[] }) => PendeteksiBarcode;

/** `BarcodeDetector` bawaan browser (Chrome/Edge Android & desktop); null di browser tanpa dukungan (Safari iOS). */
export function AmbilPendeteksiQr(): PendeteksiBarcode | null {
    const kelas = (globalThis as { BarcodeDetector?: KelasPendeteksiBarcode }).BarcodeDetector;

    try {
        return kelas ? new kelas({ formats: ['qr_code'] }) : null;
    } catch {
        return null;
    }
}

/**
 * Format ukuran berkas & durasi untuk tampilan Indonesia (PRD §17.6.7). Bukan untuk uang.
 */
const formatAngka = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

export function FormatUkuranBerkas(byte: number | null): string {
    if (byte === null) {
        return '—';
    }

    const satuan = ['B', 'KB', 'MB', 'GB', 'TB'];
    let nilai = byte;
    let indeks = 0;

    while (nilai >= 1024 && indeks < satuan.length - 1) {
        nilai /= 1024;
        indeks += 1;
    }

    return `${formatAngka.format(nilai)} ${satuan[indeks] ?? 'B'}`;
}

export function FormatDurasi(detik: number | null): string {
    if (detik === null) {
        return '—';
    }

    if (detik < 120) {
        return `${detik} detik`;
    }

    if (detik < 7200) {
        return `${Math.floor(detik / 60)} menit`;
    }

    if (detik < 172800) {
        return `${Math.floor(detik / 3600)} jam`;
    }

    return `${Math.floor(detik / 86400)} hari`;
}

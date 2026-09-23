/**
 * Format waktu tampilan Indonesia (PRD §17.6.7). Server mengirim ISO-8601 UTC; tampilan memakai WIB.
 */
const formatTanggalWaktu = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

export function FormatTanggalWaktu(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    const waktu = new Date(iso);

    if (Number.isNaN(waktu.getTime())) {
        throw new Error(`Waktu tidak valid: "${iso}"`);
    }

    return `${formatTanggalWaktu.format(waktu)} WIB`;
}

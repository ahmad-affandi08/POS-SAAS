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

const formatTanggal = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

/** Tanggal kalender tanpa jam dari API ("2027-01-01") → "1 Jan 2027". Tidak terpengaruh zona waktu peramban. */
export function FormatTanggal(tanggal: string | null): string {
    if (tanggal === null) {
        return '—';
    }

    const waktu = new Date(`${tanggal}T00:00:00Z`);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(tanggal) || Number.isNaN(waktu.getTime())) {
        throw new Error(`Tanggal tidak valid: "${tanggal}"`);
    }

    return formatTanggal.format(waktu);
}

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

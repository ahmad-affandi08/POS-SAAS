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

const formatTanggalWib = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});

/**
 * Tanggal tanpa jam → "1 Jan 2027". Menerima tanggal kalender API ("2027-01-01", tidak terpengaruh zona waktu
 * peramban) atau waktu ISO-8601 lengkap ("2026-09-26T18:17:54+00:00", ditampilkan sebagai tanggal WIB).
 */
export function FormatTanggal(tanggal: string | null): string {
    if (tanggal === null) {
        return '—';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(tanggal)) {
        const waktu = new Date(`${tanggal}T00:00:00Z`);

        if (!Number.isNaN(waktu.getTime())) {
            return formatTanggal.format(waktu);
        }
    } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/.test(tanggal)) {
        const waktu = new Date(tanggal);

        if (!Number.isNaN(waktu.getTime())) {
            return formatTanggalWib.format(waktu);
        }
    }

    throw new Error(`Tanggal tidak valid: "${tanggal}"`);
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

/**
 * Durasi dari jumlah detik ("45 detik", "12 menit", "2 jam 5 menit", "3 hari 4 jam"), untuk jeda sejak bayar pada
 * daftar void & retur (F-09, BR-09.3).
 */
export function FormatDurasi(detik: number): string {
    const total = Math.max(0, Math.floor(detik));

    if (total < 60) {
        return `${String(total)} detik`;
    }

    const menit = Math.floor(total / 60);

    if (menit < 60) {
        return `${String(menit)} menit`;
    }

    const jam = Math.floor(menit / 60);

    if (jam < 24) {
        const sisaMenit = menit % 60;

        return sisaMenit === 0 ? `${String(jam)} jam` : `${String(jam)} jam ${String(sisaMenit)} menit`;
    }

    const hari = Math.floor(jam / 24);
    const sisaJam = jam % 24;

    return sisaJam === 0 ? `${String(hari)} hari` : `${String(hari)} hari ${String(sisaJam)} jam`;
}

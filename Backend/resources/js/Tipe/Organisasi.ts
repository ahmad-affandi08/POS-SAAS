import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/** Kunci izin tenant yang dipakai layar back-office (enum IzinTenant di Backend, format D-06). */
export const IzinTenant = {
    OutletLihat: 'outlet.lihat',
    OutletKelola: 'outlet.kelola',
    PenggunaLihat: 'pengguna.lihat',
    PenggunaUndang: 'pengguna.undang',
    PenggunaUbah: 'pengguna.ubah',
    PenggunaNonaktifkan: 'pengguna.nonaktifkan',
    PeranKelola: 'peran.kelola',
    AuditLihat: 'audit.lihat',
    LanggananKelola: 'langganan.kelola',
    BantuanTiketLihat: 'bantuan.tiket.lihat',
    BantuanTiketKelola: 'bantuan.tiket.kelola',
} as const;

export type KunciIzinTenant = (typeof IzinTenant)[keyof typeof IzinTenant];

/** Hanya untuk menampilkan/menyembunyikan menu & tombol. Server tetap penentu (WajibIzinTenant). */
export function PunyaIzinTenant(akses: PropsBersamaAplikasi['Akses'], izin: KunciIzinTenant): boolean {
    return akses !== null && (akses.Pemilik || akses.Izin.includes(izin));
}

export type Pilihan = { Nilai: string; Label: string };

export type Batas = { Batas: number | null; Terpakai: number };

export type Kota = { Kode: string; Nama: string; NamaProvinsi: string | null; ZonaWaktu: string };

export type StatusOrganisasi = 'Aktif' | 'Diarsipkan';

/** Teks pemakaian batas paket, misal "2 dari 3 outlet" atau "4 pengguna (tanpa batas)". */
export function FormatBatas(batas: Batas, objek: string): string {
    return batas.Batas === null
        ? `${String(batas.Terpakai)} ${objek} (tanpa batas)`
        : `${String(batas.Terpakai)} dari ${String(batas.Batas)} ${objek}`;
}

export function CekBatasPenuh(batas: Batas): boolean {
    return batas.Batas !== null && batas.Terpakai >= batas.Batas;
}

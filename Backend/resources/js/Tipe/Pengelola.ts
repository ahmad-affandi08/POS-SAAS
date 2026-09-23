/** Props bersama semua halaman Platform Pengelola (BagikanDataInertiaPengelola). */
export type PenggunaPengelola = {
    Uuid: string;
    Nama: string;
    Email: string;
    KodePeran: string[];
    Izin: string[];
};

export type LingkunganPengelola = 'Lokal' | 'Staging' | 'Produksi';

export type PropsBersamaPengelola = {
    NamaAplikasi: string;
    Lingkungan: LingkunganPengelola;
    Kilat: string | null;
    Pengguna: PenggunaPengelola | null;
    PeringatanSuperAdmin: boolean;
    errors: Record<string, string>;
};

/** Kunci izin (enum IzinPengelola di Backend, format D-06). */
export const IzinPengelola = {
    TimAnggotaLihat: 'tim.anggota.lihat',
    TimAnggotaUndang: 'tim.anggota.undang',
    TimAnggotaNonaktifkan: 'tim.anggota.nonaktifkan',
    TimPeranTetapkan: 'tim.peran.tetapkan',
    AuditLihat: 'audit.lihat',
} as const;

export type KunciIzinPengelola = (typeof IzinPengelola)[keyof typeof IzinPengelola];

export function PunyaIzin(pengguna: PenggunaPengelola | null, izin: KunciIzinPengelola): boolean {
    return pengguna?.Izin.includes(izin) ?? false;
}

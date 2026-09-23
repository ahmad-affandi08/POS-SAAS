/** Props bersama halaman tenant (BagikanDataInertia). */
export type PenggunaAplikasi = { Uuid: string; Nama: string; Email: string; EmailTerverifikasi: boolean };

/** BR-P06.5: versi materiil dokumen legal yang diumumkan dan belum berlaku (hanya untuk Owner). */
export type PengumumanLegal = { Label: string; Versi: number; BerlakuMulai: string; Tautan: string };

export type PropsBersamaAplikasi = {
    NamaAplikasi: string;
    Kilat: string | null;
    Pengguna: PenggunaAplikasi | null;
    TenantAktif: { Nama: string } | null;
    PengumumanLegal: PengumumanLegal[];
    /** F-02: hak akses di tenant aktif (null di luar back-office). */
    Akses: { Pemilik: boolean; Izin: string[] } | null;
    errors: Record<string, string>;
};

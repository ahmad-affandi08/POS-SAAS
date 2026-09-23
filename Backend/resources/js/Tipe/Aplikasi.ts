/** Props bersama halaman tenant (BagikanDataInertia). */
export type PenggunaAplikasi = { Uuid: string; Nama: string; Email: string; EmailTerverifikasi: boolean };

export type PropsBersamaAplikasi = {
    NamaAplikasi: string;
    Kilat: string | null;
    Pengguna: PenggunaAplikasi | null;
    TenantAktif: { Nama: string } | null;
    /** F-02: hak akses di tenant aktif (null di luar back-office). */
    Akses: { Pemilik: boolean; Izin: string[] } | null;
    errors: Record<string, string>;
};

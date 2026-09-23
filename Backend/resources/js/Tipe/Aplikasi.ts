/** Props bersama halaman tenant (BagikanDataInertia). */
export type PenggunaAplikasi = { Uuid: string; Nama: string; Email: string; EmailTerverifikasi: boolean };

export type PropsBersamaAplikasi = {
    NamaAplikasi: string;
    Kilat: string | null;
    Pengguna: PenggunaAplikasi | null;
    TenantAktif: { Nama: string } | null;
    errors: Record<string, string>;
};

/** Props bersama halaman tenant (BagikanDataInertia). */
export type PenggunaAplikasi = { Uuid: string; Nama: string; Email: string; EmailTerverifikasi: boolean };

/** BR-P06.5: versi materiil dokumen legal yang diumumkan dan belum berlaku (hanya untuk Owner). */
export type PengumumanLegal = { Label: string; Versi: number; BerlakuMulai: string; Tautan: string };

/** F-00 (BR-00.7): status `Langganan` tenant aktif, untuk banner Tertunggak/Ditangguhkan. */
export type StatusLanggananTenant = 'Trial' | 'Aktif' | 'Tertunggak' | 'Ditangguhkan' | 'Berhenti' | 'Gratis';

export type TenantAktif = {
    Nama: string;
    StatusLangganan: StatusLanggananTenant | null;
    PeriodeSelesai: string | null;
    /** Saat langganan Tertunggak akan ditangguhkan (akhir periode + masa tenggang). */
    BatasTenggangPada: string | null;
};

export type PropsBersamaAplikasi = {
    NamaAplikasi: string;
    Kilat: string | null;
    Pengguna: PenggunaAplikasi | null;
    TenantAktif: TenantAktif | null;
    PengumumanLegal: PengumumanLegal[];
    /** F-02: hak akses di tenant aktif (null di luar back-office). */
    Akses: { Pemilik: boolean; Izin: string[] } | null;
    errors: Record<string, string>;
};

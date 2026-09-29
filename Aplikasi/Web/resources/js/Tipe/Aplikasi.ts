/** Props bersama halaman tenant (BagikanDataInertia). */
export type PenggunaAplikasi = { Uuid: string; Nama: string; Email: string; EmailTerverifikasi: boolean };

/** BR-P06.5: versi materiil dokumen legal yang diumumkan dan belum berlaku (hanya untuk Owner). */
export type PengumumanLegal = { Label: string; Versi: number; BerlakuMulai: string; Tautan: string };

/** F-00 (BR-00.7): status `Langganan` tenant aktif, untuk banner Tertunggak/Ditangguhkan. */
export type StatusLanggananTenant = 'Trial' | 'Aktif' | 'Tertunggak' | 'Ditangguhkan' | 'Berhenti' | 'Gratis';

export type TagihanTertundaTenant = {
    Uuid: string;
    Nomor: string;
    Total: string;
    JatuhTempoPada: string;
};

export type TenantAktif = {
    Id?: number;
    KodePelanggan?: string;
    Nama: string;
    Slug?: string;
    TautanLogo?: string | null;
    NamaPaket?: string;
    KodePaket?: string;
    StatusLangganan: StatusLanggananTenant | null;
    PeriodeSelesai: string | null;
    /** Saat langganan Tertunggak akan ditangguhkan (akhir periode + masa tenggang). */
    BatasTenggangPada: string | null;
    TagihanTertunda?: TagihanTertundaTenant | null;
};

export type PropsBersamaAplikasi = {
    NamaAplikasi: string;
    /** D-20: alamat situs pemasaran (absolut bila domain pemasaran terpisah, selain itu `/`). */
    UrlPemasaran?: string;
    Kilat: string | null;
    Pengguna: PenggunaAplikasi | null;
    TenantAktif: TenantAktif | null;
    PengumumanLegal: PengumumanLegal[];
    /** F-02: hak akses di tenant aktif (null di luar back-office). */
    Akses: { Pemilik: boolean; Izin: string[] } | null;
    /** D-23: fitur di luar paket (menu tetap tampil; klik = dialog naik paket / add-on). */
    FiturPaket?: FiturPaket | null;
    errors: Record<string, string>;
};

/** D-23: penawaran untuk satu fitur yang terkunci. */
export type PenawaranFitur = {
    Nama: string;
    Paket: { Kode: string; Nama: string; HargaBulanan: string | null } | null;
    Addon: { Kode: string; Nama: string; HargaBulanan: string } | null;
};

export type FiturPaket = { NamaPaket: string | null; Terkunci: Record<string, PenawaranFitur> };

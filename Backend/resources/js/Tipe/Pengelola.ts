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
    ReferensiLihat: 'referensi.lihat',
    ReferensiWilayahKelola: 'referensi.wilayah.kelola',
    ReferensiBankKelola: 'referensi.bank.kelola',
    ReferensiSatuanKelola: 'referensi.satuan.kelola',
    ReferensiTarifPajakAjukan: 'referensi.tarif-pajak.ajukan',
    ReferensiTarifPajakSetujui: 'referensi.tarif-pajak.setujui',
    ReferensiHariLiburAjukan: 'referensi.hari-libur.ajukan',
    ReferensiHariLiburSetujui: 'referensi.hari-libur.setujui',
    KatalogLihat: 'katalog.lihat',
    KatalogFiturKelola: 'katalog.fitur.kelola',
    KatalogPaketAjukan: 'katalog.paket.ajukan',
    KatalogPaketSetujui: 'katalog.paket.setujui',
    KatalogAddonKelola: 'katalog.addon.kelola',
    KatalogKuponKelola: 'katalog.kupon.kelola',
} as const;

/** Daftar berhalaman dari Backend (App\Http\Respons\DaftarBerhalaman). */
export type DaftarBerhalaman<T> = { Data: T[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };

export type Pilihan = { Nilai: string; Label: string };

export type KunciIzinPengelola = (typeof IzinPengelola)[keyof typeof IzinPengelola];

export function PunyaIzin(pengguna: PenggunaPengelola | null, izin: KunciIzinPengelola): boolean {
    return pengguna?.Izin.includes(izin) ?? false;
}

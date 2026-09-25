import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** F-16a pelanggan (CRM-01). */
export type StatusPelanggan = 'Aktif' | 'Diarsipkan';

export type IzinPelanggan = { Kelola: boolean; LihatPenjualan: boolean };

export type BarisPelanggan = {
    Uuid: string;
    Nama: string;
    /** Terformat `0812-3456-7890`. */
    NoHp: string;
    Email: string | null;
    TanggalLahir: string | null;
    Alamat: string | null;
    Tag: string[];
    Catatan: string | null;
    SetujuPemasaran: boolean;
    Status: StatusPelanggan;
    /** F-16b */
    Tier: { Kode: string; Nama: string } | null;
    TierTetap: boolean;
    SaldoPoin: number;
    DibuatPada: string | null;
    JumlahTransaksi: number;
    TotalBelanja: string;
    TerakhirPada: string | null;
};

export type OpsiTier = { Nilai: string; Label: string; Uuid: string };

export type PropsDaftarPelanggan = {
    Pelanggan: HasilTabel<BarisPelanggan>;
    Izin: IzinPelanggan;
    OpsiTag: string[];
    OpsiTier: OpsiTier[];
};

export type RiwayatBelanja = {
    Uuid: string;
    Nomor: string;
    TanggalBisnis: string;
    DibuatPada: string | null;
    Status: 'Lunas' | 'Void' | 'DireturSebagian' | 'Diretur';
    TotalAkhir: string;
};

export type MutasiPoin = {
    Id: number;
    Jenis: string;
    LabelJenis: string;
    Poin: number;
    Sisa: number | null;
    KedaluwarsaPada: string | null;
    Keterangan: string | null;
    DibuatPada: string | null;
};

export type PropsDetailPelanggan = {
    Pelanggan: BarisPelanggan;
    Riwayat: RiwayatBelanja[];
    RiwayatPoin: MutasiPoin[];
    OpsiTier: OpsiTier[];
    LoyaltiBerlaku: boolean;
    Izin: IzinPelanggan;
};

export type BarisTier = {
    Uuid: string;
    Kode: string;
    Nama: string;
    MinimalBelanja: string;
    PengaliPoin: string;
    Urutan: number;
    Status: StatusPelanggan;
    JumlahPelanggan: number;
};

export type PropsTierPelanggan = { Tier: BarisTier[]; FiturAktif: boolean; Izin: { Kelola: boolean } };

export type PengaturanLoyalti = {
    Aktif: boolean;
    BelanjaPerPoin: string;
    MasaBerlakuBulan: number;
    BulanEvaluasiTier: number;
};

export type PropsPengaturanLoyalti = { Pengaturan: PengaturanLoyalti; FiturAktif: boolean; Izin: { Kelola: boolean } };

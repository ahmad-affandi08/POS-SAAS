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
    DibuatPada: string | null;
    JumlahTransaksi: number;
    TotalBelanja: string;
    TerakhirPada: string | null;
};

export type PropsDaftarPelanggan = { Pelanggan: HasilTabel<BarisPelanggan>; Izin: IzinPelanggan; OpsiTag: string[] };

export type RiwayatBelanja = {
    Uuid: string;
    Nomor: string;
    TanggalBisnis: string;
    DibuatPada: string | null;
    Status: 'Lunas' | 'Void' | 'DireturSebagian' | 'Diretur';
    TotalAkhir: string;
};

export type PropsDetailPelanggan = { Pelanggan: BarisPelanggan; Riwayat: RiwayatBelanja[]; Izin: IzinPelanggan };

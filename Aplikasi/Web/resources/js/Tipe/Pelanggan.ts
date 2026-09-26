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
    /** F-12: null = tidak boleh bayar tempo. */
    LimitKredit: string | null;
    TerminHari: number;
    DibuatPada: string | null;
    JumlahTransaksi: number;
    TotalBelanja: string;
    TerakhirPada: string | null;
    /** F-16d: saldo deposit (bisa minus bila dipakai dua perangkat bersamaan). */
    SaldoDeposit: string;
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
    /** F-12: posisi kredit pelanggan. */
    Kredit: KreditPelanggan | null;
    /** F-16d bagian 1: saldo & riwayat deposit. */
    Deposit: DepositPelanggan;
    Izin: IzinPelanggan & { KelolaDeposit: boolean };
};

export type MutasiDeposit = {
    Uuid: string;
    Jenis: 'Isi' | 'BatalIsi' | 'Pemakaian' | 'BatalPemakaian' | 'Refund' | 'Penarikan' | 'Penyesuaian';
    LabelJenis: string;
    Jumlah: string;
    SaldoSetelah: string;
    NomorSumber: string | null;
    Tanggal: string;
    Keterangan: string | null;
    DibuatPada: string | null;
};

export type DepositPelanggan = {
    Saldo: string;
    /** Paket usaha termasuk fitur deposit pelanggan. */
    Berlaku: boolean;
    Riwayat: MutasiDeposit[];
    /** Akun kas/bank sumber penarikan (kosong bila tidak berizin). */
    AkunKasBank: { Uuid: string; Kode: string; Nama: string }[];
};

export type StatusIsiDeposit = 'Diterima' | 'Dibatalkan';

export type BarisIsiDeposit = {
    Uuid: string;
    Nomor: string;
    Pelanggan: { Uuid: string; Nama: string } | null;
    NamaMetode: string;
    Jumlah: string;
    Status: StatusIsiDeposit;
    LabelStatus: string;
    TanggalBisnis: string;
    PerluTinjauan: boolean;
    AlasanTinjauan: string | null;
    AlasanBatal: string | null;
    DiterimaPada: string;
};

export type PropsIsiDeposit = {
    IsiDeposit: HasilTabel<BarisIsiDeposit>;
    Izin: { KelolaDeposit: boolean };
};

export type KreditPelanggan = { LimitKredit: string | null; SisaPiutang: string; HariLewatJatuhTempo: number };

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
export type PropsBuatTierPelanggan = Pick<PropsTierPelanggan, 'FiturAktif'>;

export type PengaturanLoyalti = {
    Aktif: boolean;
    BelanjaPerPoin: string;
    MasaBerlakuBulan: number;
    BulanEvaluasiTier: number;
    /** Rupiah per poin saat ditukar sebagai diskon (string desimal). */
    NilaiTukarPoin: string;
    MinimalTukarPoin: number;
};

export type PropsPengaturanLoyalti = { Pengaturan: PengaturanLoyalti; FiturAktif: boolean; Izin: { Kelola: boolean } };

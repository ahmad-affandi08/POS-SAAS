import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** F-16c: promo back-office (`/kelola/promo`). */

export type JenisAksiPromo =
    | 'DiskonPersenItem'
    | 'DiskonTetapItem'
    | 'HargaSpesial'
    | 'DiskonPersenPesanan'
    | 'DiskonTetapPesanan'
    | 'BeliXGratisY'
    | 'BundelHargaTetap';

export type JenisKondisiPromo = 'Semua' | 'Produk' | 'Kategori';

export type ModeResolusiPromo = 'Terbaik' | 'PrioritasKetat';

export type DefinisiPromo = {
    Hari: number[];
    JamMulai: string | null;
    JamSelesai: string | null;
    Outlet: string[];
    Kanal: string[];
    Tier: string[];
    MinimalSubtotal: string;
    Kondisi: { Jenis: JenisKondisiPromo; Uuid: string[]; JumlahMinimal: string };
    Aksi: {
        Jenis: JenisAksiPromo;
        Persen?: string;
        Jumlah?: string;
        Harga?: string;
        Beli?: number;
        Gratis?: number;
        PersenGratis?: string;
    };
    BatasPerTransaksi: number | null;
    /** F-16c bagian 2: promo hanya berlaku dengan kode voucher. */
    WajibVoucher?: boolean;
    /** F-16c bagian 3: semua pembayaran wajib memakai salah satu metode ini (Uuid). */
    MetodeBayar?: string[];
    UlangTahun?: { Jenis: JenisUlangTahunPromo; Hari?: number };
    TransaksiPertama?: boolean;
    BatasPerPelanggan?: { Jumlah: number; Periode: PeriodeBatasPelangganPromo };
};

export type JenisUlangTahunPromo = 'Hari' | 'Rentang' | 'Bulan';
export type PeriodeBatasPelangganPromo = 'Hari' | 'Promo';

export type BarisPromo = {
    Uuid: string;
    Kode: string;
    Nama: string;
    JenisAksi: JenisAksiPromo | null;
    LabelAksi: string;
    Prioritas: number;
    Eksklusif: boolean;
    MulaiPada: string | null;
    SelesaiPada: string | null;
    Kuota: number | null;
    KuotaTerpakai: number;
    Status: 'Aktif' | 'Diarsipkan';
    WajibVoucher: boolean;
    JumlahPakai: number;
    TotalDiskon: string;
    Definisi: DefinisiPromo | null;
};

export type PropsDaftarPromo = {
    Promo: BarisPromo[];
    ModeResolusi: ModeResolusiPromo;
    FiturAktif: boolean;
    Izin: { Kelola: boolean };
};

export type OpsiNilai = { Nilai: string; Label: string };

export type PromoFormulir = BarisPromo & {
    Definisi: DefinisiPromo;
    TanggalMulai: string | null;
    TanggalSelesai: string | null;
    NamaProduk: Record<string, string>;
};

export type PropsFormulirPromo = {
    Promo: PromoFormulir | null;
    OpsiOutlet: OpsiNilai[];
    OpsiTier: OpsiNilai[];
    OpsiKategori: OpsiNilai[];
    OpsiKanal: OpsiNilai[];
    OpsiMetodeBayar: OpsiNilai[];
    OpsiUlangTahun: { Nilai: JenisUlangTahunPromo; Label: string }[];
    OpsiPeriodeBatas: { Nilai: PeriodeBatasPelangganPromo; Label: string }[];
    FiturAktif: boolean;
};

/** F-16c bagian 2: voucher promo (`/kelola/promo/{promo}/voucher`). */
export type BarisVoucher = {
    Uuid: string;
    Kode: string;
    MaksimalPakai: number | null;
    JumlahDipakai: number;
    /** Pesanan kasir yang masih berlaku (belum menjadi penjualan). */
    Dipesan: number;
    KedaluwarsaPada: string | null;
    Status: 'Aktif' | 'Nonaktif';
    DibuatPada: string | null;
};

export type PropsVoucherPromo = {
    Promo: BarisPromo & { Definisi: null };
    Voucher?: HasilTabel<BarisVoucher>;
    Ringkasan: { Total: number; Aktif: number; Dipakai: number };
    JumlahMaksimal: number;
    Izin: { Kelola: boolean };
};

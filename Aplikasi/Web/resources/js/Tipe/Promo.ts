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
};

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
    FiturAktif: boolean;
};

import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** Tipe laporan F-14a (dasbor pemilik, laporan penjualan, pajak, stok). Uang = string desimal dari server. */

export type OpsiLaporan = { Nilai: string; Label: string };

/** Angka penjualan teragregasi (`DataAgregatPenjualan::KeLarik`). */
export type AngkaPenjualan = {
    Kotor: string;
    Diskon: string;
    Retur: string;
    Bersih: string;
    Pajak: string;
    BiayaLayanan: string;
    Hpp: string;
    LabaKotor: string;
    JumlahTransaksi: number;
    JumlahRetur: number;
    RataRataKeranjang: string;
    JumlahBarang: string | null;
};

export type TitikGrafik = { Tanggal: string; Bersih: string; LabaKotor: string; JumlahTransaksi: number };

export type BarisStokKritis = {
    Kunci: string;
    UuidProduk: string;
    NamaProduk: string;
    Sku: string | null;
    SimbolSatuan: string;
    UuidGudang: string;
    NamaGudang: string;
    NamaOutlet: string;
    Saldo: string;
    StokMinimum: string;
    Kekurangan: string;
};

export type StokKritis = { Jumlah: number; Baris: BarisStokKritis[] };

export type BarisBatchKedaluwarsa = {
    Kunci: string;
    UuidProduk: string;
    NamaProduk: string;
    Sku: string | null;
    SimbolSatuan: string;
    UuidGudang: string;
    NamaGudang: string;
    NamaOutlet: string;
    NomorBatch: string;
    TanggalKedaluwarsa: string;
    SisaHari: number;
    Status: 'Lewat' | 'Segera' | 'Mendekati';
    Sisa: string;
};

export type BatchKedaluwarsa = { Jumlah: number; JumlahLewat: number; Baris: BarisBatchKedaluwarsa[] };

export type DasborPemilik = {
    Tanggal: string;
    HariIni: AngkaPenjualan;
    Kemarin: AngkaPenjualan;
    MingguLalu: AngkaPenjualan;
    Grafik: TitikGrafik[];
    ProdukTerlaris: { Kunci: string; NamaProduk: string; Qty: string; Bersih: string }[];
    PerOutlet: { Kunci: string; NamaOutlet: string; Bersih: string; JumlahTransaksi: number }[];
    StokKritis: StokKritis | null;
    Shift: {
        JumlahTerbuka: number;
        Terbuka: { Uuid: string; NamaOutlet: string; NamaKasir: string; DibukaPada: string }[];
        Tertutup: {
            Uuid: string;
            NamaOutlet: string;
            NamaKasir: string;
            DitutupPada: string | null;
            Selisih: string | null;
        }[];
    };
    JumlahPerluTinjauan: number;
};

export type TabLaporanPenjualan =
    'harian' | 'produk' | 'kategori' | 'jam' | 'kasir' | 'kanal' | 'metode' | 'diskon' | 'anti-fraud' | 'abc' | 'menu';

export type SaringLaporanPenjualan = {
    Tab: TabLaporanPenjualan;
    Dari: string;
    Sampai: string;
    Outlet: string;
    Kasir: string;
    Kanal: string;
};

export type BarisHarian = AngkaPenjualan & { Tanggal: string };
export type BarisProdukLaporan = {
    IdProduk: number;
    NamaProduk: string;
    Qty: string;
    Kotor: string;
    Diskon: string;
    Retur: string;
    Bersih: string;
    Pajak: string;
    BiayaLayanan: string;
    Hpp: string;
    LabaKotor: string;
    JumlahTransaksi: number;
    JumlahRetur: number;
};
export type BarisKategoriLaporan = {
    Kunci: string;
    NamaKategori: string;
    JumlahProduk: number;
    Qty: string;
    Kotor: string;
    Diskon: string;
    Retur: string;
    Bersih: string;
    Pajak: string;
    Hpp: string;
    LabaKotor: string;
};
export type SelJam = { Hari: number; Jam: number; Bersih: string; JumlahTransaksi: number };
export type IsiJam = { Sel: SelJam[]; PerJam: { Jam: number; Bersih: string; JumlahTransaksi: number }[] };
export type BarisKasirLaporan = AngkaPenjualan & { Kunci: string; NamaKasir: string };
export type BarisKanalLaporan = AngkaPenjualan & { Kunci: string; LabelKanal: string };
export type BarisMetodeLaporan = {
    Kunci: string;
    NamaMetode: string;
    LabelJenis: string;
    Diterima: string;
    Refund: string;
    Bersih: string;
    JumlahTransaksi: number;
};
export type BarisDiskonLaporan = {
    Kunci: string;
    NamaKasir: string;
    JumlahTransaksi: number;
    JumlahBerdiskon: number;
    JumlahDisetujui: number;
    DiskonBaris: string;
    DiskonPesanan: string;
    TotalDiskon: string;
    Kotor: string;
};

/** F-14 anti-fraud (OWN-09): pola & skor risiko per kasir; skor adalah petunjuk untuk diperiksa, bukan bukti. */
export type BarisAntiFraudLaporan = {
    Kunci: string;
    NamaKasir: string;
    JumlahTransaksi: number;
    JumlahVoid: number;
    NilaiVoid: string;
    VoidCepatTunai: number;
    JumlahRetur: number;
    NilaiRetur: string;
    JumlahBerdiskon: number;
    TotalDiskon: string;
    BukaLaciManual: number;
    ShiftSelisihKurang: number;
    SelisihKurang: string;
    Skor: number;
    Tingkat: 'Rendah' | 'Sedang' | 'Tinggi';
    Alasan: string[];
};

/** X6 analisis ABC (Pareto): A ±80% omzet, B sampai 95%, C sisanya. Persen = string 2 desimal ("45.67"). */
export type KelasAbc = 'A' | 'B' | 'C';
export type BarisAbcLaporan = {
    IdProduk: number;
    NamaProduk: string;
    Qty: string;
    Bersih: string;
    Porsi: string;
    PorsiKumulatif: string;
    Kelas: KelasAbc;
};
export type IsiAbc = { Baris: BarisAbcLaporan[]; Ringkasan: Record<KelasAbc, { Jumlah: number; Bersih: string }> };

/** X6 menu engineering (Kasavana & Smith): populer × margin kontribusi per unit. */
export type KelasMenu = 'Star' | 'Plowhorse' | 'Puzzle' | 'Dog';
export type BarisMenuLaporan = {
    IdProduk: number;
    NamaProduk: string;
    Qty: string;
    Bersih: string;
    Hpp: string;
    MarginPerUnit: string;
    PorsiQty: string;
    Populer: boolean;
    MarginTinggi: boolean;
    Kelas: KelasMenu;
};
export type IsiMenu = { Baris: BarisMenuLaporan[]; BatasPorsiQty: string; RataRataMargin: string };

export type PropsLaporanPenjualan = {
    Saring: SaringLaporanPenjualan;
    Peringatan: string | null;
    MaksHari: number;
    OpsiOutlet: OpsiLaporan[];
    OpsiKasir: OpsiLaporan[];
    OpsiKanal: OpsiLaporan[];
    Total: AngkaPenjualan;
    Isi:
        | BarisHarian[]
        | HasilTabel<BarisProdukLaporan>
        | BarisKategoriLaporan[]
        | IsiJam
        | BarisKasirLaporan[]
        | BarisKanalLaporan[]
        | BarisMetodeLaporan[]
        | BarisDiskonLaporan[]
        | BarisAntiFraudLaporan[]
        | IsiAbc
        | IsiMenu;
};

export type BarisPajakLaporan = {
    Kunci: string;
    Bulan: string;
    KodeJenisPajak: string;
    NamaJenisPajak: string;
    LabelKategori: string;
    Tarif: string;
    NamaOutlet?: string;
    Dpp: string;
    Pajak: string;
    DppRetur: string;
    PajakRetur: string;
    DppBersih: string;
    PajakBersih: string;
    JumlahTransaksi: number;
};

export type PropsLaporanPajak = {
    Saring: { Dari: string; Sampai: string; Outlet: string };
    Peringatan: string | null;
    OpsiOutlet: OpsiLaporan[];
    Pbjt: BarisPajakLaporan[];
    Ppn: BarisPajakLaporan[];
};

/** Ringkasan kesiapan ekspor Faktur Pajak Keluaran Coretax (GET /kelola/laporan/pajak/faktur-keluaran, PRD v3.12). */
export type RingkasanFakturPajak = {
    JumlahDiperiksa: number;
    JumlahSiap: number;
    TotalDpp: string;
    TotalPpn: string;
    BisaDiekspor: boolean;
    MasalahUmum: string[];
    MasalahFaktur: { Nomor: string; Alasan: string[] }[];
    Peringatan: string[];
    Selisih: { Nomor: string; SelisihDpp: string; SelisihPpn: string }[];
};

export type NilaiPersediaan = {
    Total: { Nilai: string; JumlahProduk: number };
    PerGudang: { Kunci: string; NamaGudang: string; NamaOutlet: string; JumlahProduk: number; Nilai: string }[];
    PerKategori: { Kunci: string; NamaKategori: string; JumlahProduk: number; Nilai: string }[];
};

/** X6 saran restock: laju pemakaian `HariDasar` hari terakhir → saran beli untuk `HariCakupan` hari ke depan. */
export type BarisSaranRestock = {
    Kunci: string;
    UuidProduk: string;
    NamaProduk: string;
    Sku: string | null;
    SimbolSatuan: string;
    UuidGudang: string;
    NamaGudang: string;
    NamaOutlet: string;
    Pakai: string;
    RataPerHari: string;
    /** X6 musiman: pengali laju dari periode yang sama tahun lalu (1.00 = tanpa data/tanpa musim). */
    FaktorMusim: string;
    RataPerkiraan: string;
    Saldo: string;
    HariHabis: number | null;
    SaranBeli: string;
};
/** Pembanding tahun lalu: `Lebaran` = selaras Idul Fitri (digeser `SelisihHari`), `TahunLalu` = 364 hari. */
export type MusimRestock = { Jenis: 'Lebaran' | 'TahunLalu'; SelisihHari: number; Lebaran: string | null };
export type SaranRestock = { HariDasar: number; HariCakupan: number; Musim: MusimRestock; Baris: BarisSaranRestock[] };

export type PropsLaporanStok = {
    Saring: { Tab: 'nilai' | 'kritis' | 'kedaluwarsa' | 'restock'; Tanggal: string; Gudang: string; Hari: number };
    OpsiGudang: OpsiLaporan[];
    Nilai: NilaiPersediaan | null;
    Kritis: StokKritis | null;
    Kedaluwarsa: BatchKedaluwarsa | null;
    Restock: SaranRestock | null;
};

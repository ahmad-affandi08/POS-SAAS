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

export type TabLaporanPenjualan = 'harian' | 'produk' | 'kategori' | 'jam' | 'kasir' | 'kanal' | 'metode' | 'diskon';

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
        | BarisDiskonLaporan[];
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

export type NilaiPersediaan = {
    Total: { Nilai: string; JumlahProduk: number };
    PerGudang: { Kunci: string; NamaGudang: string; NamaOutlet: string; JumlahProduk: number; Nilai: string }[];
    PerKategori: { Kunci: string; NamaKategori: string; JumlahProduk: number; Nilai: string }[];
};

export type PropsLaporanStok = {
    Saring: { Tab: 'nilai' | 'kritis'; Tanggal: string; Gudang: string };
    OpsiGudang: OpsiLaporan[];
    Nilai: NilaiPersediaan | null;
    Kritis: StokKritis | null;
};

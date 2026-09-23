import type { Pilihan } from '@/Tipe/Pengelola';

/** Isi satu versi template sektor (P-03, kolom `TemplateSektorVersi.Isi`). */
export type AkunTemplate = { Kode: string; Nama: string; Tipe: string; SaldoNormal: string; Kontra: boolean };

export type KelompokPajakTemplate = {
    Nama: string;
    Detail: { KodeJenisPajak: string; DasarPengenaan: string; Urutan: number }[];
};

export type PengaturanTemplate = {
    PembulatanTunai: { Kelipatan: number; Arah: string };
    PersenBiayaLayanan: string;
    BiayaLayananMasukDpp: boolean;
    StokBolehMinus: boolean;
    MetodeHpp: string;
    HargaTermasukPajak: boolean;
};

export type JenisProdukContoh = 'Stok' | 'NonStok' | 'Jasa';

/** Produk contoh untuk panduan awal tenant (F-01 langkah 4). Harga string desimal Rupiah, bukan number. */
export type ProdukContohTemplate = {
    Nama: string;
    Kategori: string | null;
    Harga: string;
    KodeSatuan: string;
    Jenis: JenisProdukContoh;
};

export type IsiBisnisTemplate = {
    ModeKasir: string[];
    ModeKasirDefault: string | null;
    KunciFitur: string[];
    Kategori: string[];
    KodeSatuan: string[];
    Pengaturan: PengaturanTemplate;
    StasiunDapur: string[];
    AlasanVoid: string[];
    AlasanPenyesuaian: string[];
    LaporanUnggulan: string[];
    ProdukContoh: ProdukContohTemplate[];
};

export type IsiAkunTemplate = {
    Akun: AkunTemplate[];
    PemetaanAkun: Record<string, string>;
    KelompokPajak: KelompokPajakTemplate[];
};

export type IsiTemplate = IsiBisnisTemplate & IsiAkunTemplate;

export type GalatValidasi = { Bagian: string; Pesan: string };

export type PilihanEditorTemplate = {
    ModeKasir: Pilihan[];
    TipeAkun: (Pilihan & { DigitAwal: string; SaldoNormal: string })[];
    SaldoNormal: Pilihan[];
    PeranAkun: (Pilihan & { Tipe: string; WajibKontra: boolean })[];
    ArahPembulatan: Pilihan[];
    MetodeHpp: Pilihan[];
    DasarPengenaan: Pilihan[];
    LaporanUnggulan: Pilihan[];
    Fitur: (Pilihan & { Kelompok: string })[];
    Satuan: Pilihan[];
    JenisPajak: Pilihan[];
    JenisProdukContoh: Pilihan[];
};

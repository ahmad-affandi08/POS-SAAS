import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** F-07b penjualan dari POS (back-office, baca saja). Uang & jumlah = string desimal dari server; tidak pernah number. */

export type StatusPenjualan = 'Lunas' | 'Void' | 'Diretur';

export type BarisPenjualan = {
    Uuid: string;
    Nomor: string;
    DibuatOfflinePada: string;
    TanggalBisnis: string;
    NamaOutlet: string;
    NamaKasir: string;
    Kanal: string;
    LabelKanal: string;
    TotalAkhir: string;
    Metode: string[];
    Status: StatusPenjualan;
    LabelStatus: string;
    PerluTinjauan: boolean;
};

type Opsi = { Nilai: string; Label: string };

export type PropsDaftarPenjualan = {
    Penjualan: HasilTabel<BarisPenjualan>;
    OpsiOutlet: { Uuid: string; Nama: string }[];
    OpsiStatus: Opsi[];
    OpsiKanal: Opsi[];
};

export type RingkasanPenjualan = {
    Uuid: string;
    Nomor: string;
    Status: StatusPenjualan;
    LabelStatus: string;
    Kanal: string;
    LabelKanal: string;
    NamaOutlet: string;
    Perangkat: string;
    NamaKasir: string;
    NamaPenyetujuDiskon: string | null;
    DibuatOfflinePada: string;
    DiterimaPada: string;
    TanggalBisnis: string;
    HargaTermasukPajak: boolean;
    PersenBiayaLayanan: string;
    Subtotal: string;
    DiskonBaris: string;
    DiskonPesanan: string;
    TotalDiskon: string;
    BiayaLayanan: string;
    TotalPajak: string;
    Pembulatan: string;
    TotalAkhir: string;
    TotalDibayar: string;
    Kembalian: string;
    TotalHpp: string;
    Catatan: string | null;
    PerluTinjauan: boolean;
    AlasanTinjauan: string | null;
    UuidShift: string | null;
};

export type BarisDetailPenjualan = {
    Uuid: string;
    NamaProduk: string;
    Jumlah: string;
    SimbolSatuan: string;
    HargaSatuan: string;
    HargaPilihan: string;
    Pilihan: string[];
    Bruto: string;
    JumlahDiskon: string;
    JumlahDiskonPesanan: string;
    JumlahPajak: string;
    TotalBaris: string;
    HppSatuan: string;
    TotalHpp: string;
    Catatan: string | null;
};

export type BarisPajakPenjualan = {
    KodeJenisPajak: string;
    Tarif: string;
    DasarPengenaan: string;
    Dpp: string;
    Jumlah: string;
};

export type BarisPembayaranPenjualan = {
    Uuid: string;
    NamaMetode: string;
    LabelJenis: string;
    Jumlah: string;
    Referensi: string | null;
};

export type BarisMutasiPenjualan = {
    Kunci: string;
    NamaProduk: string;
    NamaGudang: string;
    Jumlah: string;
    SimbolSatuan: string;
    TotalHpp: string;
    TautanKartuStok: string | null;
};

export type PropsDetailPenjualan = {
    Penjualan: RingkasanPenjualan;
    Baris: BarisDetailPenjualan[];
    Pajak: BarisPajakPenjualan[];
    Pembayaran: BarisPembayaranPenjualan[];
    MutasiStok: BarisMutasiPenjualan[];
    Jurnal: { Uuid: string; Nomor: string }[];
};

/** Penjualan satu shift (detail shift F-06). */
export type PenjualanShift = {
    Daftar: BarisPenjualan[];
    JumlahTransaksi: number;
    TotalPenjualan: string;
};

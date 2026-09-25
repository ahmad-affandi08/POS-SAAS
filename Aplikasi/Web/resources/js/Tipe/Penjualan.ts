import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** F-07b penjualan dari POS (back-office, baca saja). Uang & jumlah = string desimal dari server; tidak pernah number. */

/** F-09: `DireturSebagian` = sebagian barang diretur; `Diretur` = semua baris habis diretur. */
export type StatusPenjualan = 'Lunas' | 'Void' | 'DireturSebagian' | 'Diretur';

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
    /** Alasan tinjauan dengan label manusiawi (kode mesin tidak ditampilkan). */
    DaftarAlasanTinjauan: AlasanTinjauan[];
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
    /** F-09: jumlah (satuan jual) yang sudah diretur. */
    JumlahDiretur: string;
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

/** F-09: void seluruh penjualan (satu per penjualan). */
export type VoidPenjualan = {
    Uuid: string;
    Alasan: string;
    NamaKasir: string;
    NamaPenyetuju: string;
    DivoidPada: string;
    RefundTunai: string;
    RefundNonTunai: string;
    /** Detik dari waktu penjualan sampai void (pola anti-fraud BR-09.3). */
    JedaDetik: number;
};

/** F-09: retur yang merujuk penjualan ini. */
export type ReturRingkasPenjualan = {
    Uuid: string;
    Nomor: string;
    DibuatOfflinePada: string;
    NamaKasir: string;
    Alasan: string;
    LabelMetodeRefund: string;
    TotalRefund: string;
};

export type PropsDetailPenjualan = {
    Penjualan: RingkasanPenjualan;
    Baris: BarisDetailPenjualan[];
    Pajak: BarisPajakPenjualan[];
    Pembayaran: BarisPembayaranPenjualan[];
    MutasiStok: BarisMutasiPenjualan[];
    Jurnal: { Uuid: string; Nomor: string }[];
    Void: VoidPenjualan | null;
    Retur: ReturRingkasPenjualan[];
};

/** F-09: baris daftar Void & Retur (dasar laporan anti-fraud BR-09.3). */
export type JenisVoidRetur = 'Void' | 'Retur';

export type BarisVoidRetur = {
    Kunci: string;
    Jenis: JenisVoidRetur;
    Uuid: string;
    Waktu: string;
    TanggalBisnis: string;
    /** Void: nomor penjualan; retur: nomor retur. */
    Nomor: string;
    NomorPenjualan: string;
    UuidPenjualan: string;
    Tautan: string;
    NamaOutlet: string;
    NamaKasir: string;
    NamaPenyetuju: string;
    Nominal: string;
    RefundTunai: string;
    Alasan: string;
    JedaDetik: number;
};

export type PropsDaftarVoidRetur = {
    VoidRetur: HasilTabel<BarisVoidRetur>;
    OpsiOutlet: { Uuid: string; Nama: string }[];
};

export type RingkasanRetur = {
    Uuid: string;
    Nomor: string;
    LabelStatus: string;
    LabelMetodeRefund: string;
    UuidPenjualan: string;
    NomorPenjualan: string;
    WaktuPenjualan: string;
    NamaOutlet: string;
    Perangkat: string;
    NamaKasir: string;
    NamaPenyetuju: string;
    Alasan: string;
    DibuatOfflinePada: string;
    DiterimaPada: string;
    TanggalBisnis: string;
    TotalNilai: string;
    TotalPajak: string;
    TotalBiayaLayanan: string;
    TotalRefund: string;
    RefundTunai: string;
    TotalHpp: string;
    PerluTinjauan: boolean;
    AlasanTinjauan: string | null;
    /** Alasan tinjauan dengan label manusiawi (kode mesin tidak ditampilkan). */
    DaftarAlasanTinjauan: AlasanTinjauan[];
    UuidShift: string | null;
};

export type BarisDetailRetur = {
    Uuid: string;
    NamaProduk: string;
    Jumlah: string;
    Kondisi: 'LayakJual' | 'Rusak';
    LabelKondisi: string;
    NamaGudang: string | null;
    NilaiBaris: string;
    Pajak: string;
    BiayaLayanan: string;
    TotalHpp: string;
};

export type BarisRefundRetur = {
    Uuid: string;
    NamaMetode: string;
    LabelJenis: string;
    Jumlah: string;
};

export type PropsDetailRetur = {
    Retur: RingkasanRetur;
    Baris: BarisDetailRetur[];
    Refund: BarisRefundRetur[];
    MutasiStok: BarisMutasiPenjualan[];
    Jurnal: { Uuid: string; Nomor: string }[];
};

/** Satu alasan tinjauan dokumen POS: `Kode` null bila tidak dikenal. */
export type AlasanTinjauan = {
    Kode: string | null;
    Label: string;
    Keterangan: string;
};

/** Penjualan satu shift (detail shift F-06). */
export type PenjualanShift = {
    /** Paling banyak 200 penjualan terakhir shift. */
    Daftar: BarisPenjualan[];
    /** Ada penjualan shift yang tidak ikut `Daftar` (lebih dari 200). */
    DaftarTerpotong: boolean;
    JumlahTransaksi: number;
    TotalPenjualan: string;
};

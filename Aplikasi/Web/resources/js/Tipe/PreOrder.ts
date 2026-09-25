import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** F-12 bagian 2: pre-order & uang muka (`/kelola/pre-order`). */
export type StatusPreOrder = 'Dipesan' | 'Siap' | 'Diambil' | 'Dibatalkan';

export type Opsi = { Nilai: string; Label: string };

export type BarisPreOrder = {
    Uuid: string;
    Nomor: string;
    Status: StatusPreOrder;
    LabelStatus: string;
    TanggalAmbil: string;
    TanggalPesan: string;
    Pelanggan: string;
    NoHpPelanggan: string | null;
    Outlet: string;
    TotalPesanan: string;
    UangMuka: string;
    SisaUangMuka: string;
};

export type PropsDaftarPreOrder = {
    Pesanan?: HasilTabel<BarisPreOrder>;
    OpsiStatus: Opsi[];
};

export type DetailPreOrder = BarisPreOrder & {
    Catatan: string | null;
    DipesanPada: string;
    SiapPada: string | null;
    DiambilPada: string | null;
    DibatalkanPada: string | null;
    AlasanBatal: string | null;
    UangMukaTerpakai: string;
    UangMukaDikembalikan: string;
    UangMukaHangus: string;
    Penjualan: { Uuid: string; Nomor: string; TotalAkhir: string } | null;
    Baris: {
        Uuid: string;
        NamaProduk: string;
        Jumlah: string;
        HargaSatuan: string;
        HargaPilihan: string;
        Pilihan: string[];
        Catatan: string | null;
    }[];
    Pembayaran: { Uuid: string; Metode: string; Jumlah: string; Referensi: string | null }[];
};

export type PropsDetailPreOrder = {
    Pesanan: DetailPreOrder;
    OpsiAkun: { Uuid: string; Kode: string; Nama: string }[];
    OpsiCara: Opsi[];
    Izin: { Siap: boolean; Selesaikan: boolean };
};

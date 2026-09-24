/**
 * Kontrak props & JSON halaman persediaan F-05a (DesainF05a E): stok awal, impor stok awal, saldo stok, kartu stok,
 * dan pengaturan persediaan. Desimal selalu string: uang "12345.68", jumlah "10.0000", HPP "1234.568000".
 * Tanggal `YYYY-MM-DD`; cap waktu ISO UTC. Pemilik file: Tim 0 (perubahan lewat permintaan ke lead).
 */
import type { DaftarBerhalaman } from '@/Tipe/Pengelola';

export type StatusStokAwal = 'Draf' | 'Memproses' | 'Diposting' | 'Dibatalkan' | 'Dibuang';
export type SumberStokAwal = 'Manual' | 'Impor';
export type PelacakanProduk = 'Tidak' | 'Batch' | 'Seri';
export type MetodeHpp = 'RataRata' | 'Fifo';
export type OpsiGudang = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Jenis: string;
    NamaOutlet: string | null;
    Aktif: boolean;
};
export type KesiapanAkun = { Siap: boolean; PeranBelumDipetakan: { Kunci: string; Label: string }[] };
export type IzinPersediaan = {
    Lihat: boolean;
    Kelola: boolean;
    PostingStokAwal: boolean;
    LihatJurnal: boolean;
    UbahPengaturan: boolean;
};

export type BarisDaftarStokAwal = {
    Uuid: string;
    Nomor: string | null;
    Tanggal: string;
    NamaGudang: string;
    NamaOutlet: string | null;
    Status: StatusStokAwal;
    LabelStatus: string;
    Sumber: SumberStokAwal;
    JumlahBaris: number;
    TotalNilai: string;
    DibuatOleh: string | null;
    DiubahPada: string;
};
export type SaringStokAwal = { Kata: string; Status: StatusStokAwal | 'Semua'; UuidGudang: string | null };
export type PropsDaftarStokAwal = {
    StokAwal: DaftarBerhalaman<BarisDaftarStokAwal>;
    Saring: SaringStokAwal;
    OpsiGudang: OpsiGudang[];
    Izin: IzinPersediaan;
    KesiapanAkun: KesiapanAkun;
};

export type BarisFormStokAwal = {
    UuidProduk: string;
    NamaProduk: string;
    Sku: string | null;
    SimbolSatuan: string;
    BolehDesimal: boolean;
    Pelacakan: PelacakanProduk;
    SaldoDiGudang: string | null;
    Jumlah: string;
    HppSatuan: string;
    NomorBatch: string | null;
    TanggalKedaluwarsa: string | null;
    NomorSeri: string[];
};
export type PropsFormStokAwal = {
    Mode: 'Buat' | 'Ubah';
    StokAwal: {
        Uuid: string;
        UuidGudang: string;
        Tanggal: string;
        Catatan: string | null;
        VersiDiubahPada: string;
        Baris: BarisFormStokAwal[];
    } | null;
    OpsiGudang: OpsiGudang[];
    HariIni: string;
    BatasBaris: number;
    MaksimalNomorSeriPerBaris: number;
    WajibKedaluwarsaBatch: boolean;
    KesiapanAkun: KesiapanAkun;
};
export type MasukanStokAwal = {
    Uuid?: string;
    UuidGudang: string;
    Tanggal: string;
    Catatan: string | null;
    VersiDiubahPada?: string;
    Baris: {
        UuidProduk: string;
        Jumlah: string;
        HppSatuan: string;
        NomorBatch: string | null;
        TanggalKedaluwarsa: string | null;
        NomorSeri: string[];
    }[];
};

export type PropsDetailStokAwal = {
    StokAwal: {
        Uuid: string;
        Nomor: string | null;
        Status: StatusStokAwal;
        LabelStatus: string;
        Sumber: SumberStokAwal;
        UuidImpor: string | null;
        NamaGudang: string;
        NamaOutlet: string | null;
        Tanggal: string;
        Catatan: string | null;
        JumlahBaris: number;
        TotalNilai: string;
        PesanGalat: string | null;
        DibuatOleh: string | null;
        DibuatPada: string;
        DipostingOleh: string | null;
        DipostingPada: string | null;
        DibatalkanOleh: string | null;
        DibatalkanPada: string | null;
        AlasanBatal: string | null;
        VersiDiubahPada: string;
    };
    Baris: {
        Urutan: number;
        UuidProduk: string;
        NamaProduk: string;
        Sku: string | null;
        SimbolSatuan: string;
        Pelacakan: PelacakanProduk;
        Jumlah: string;
        HppSatuan: string;
        Nilai: string;
        NomorBatch: string | null;
        TanggalKedaluwarsa: string | null;
        NomorSeri: string[];
    }[];
    Jurnal: {
        Uuid: string;
        Nomor: string;
        Tanggal: string;
        Keterangan: string;
        TotalDebit: string;
        Pembalik: boolean;
    }[];
    Riwayat: {
        StatusDari: StatusStokAwal | null;
        StatusKe: StatusStokAwal;
        LabelStatusKe: string;
        Oleh: string | null;
        Pada: string;
        Alasan: string | null;
    }[];
    Tindakan: { Ubah: boolean; Buang: boolean; Posting: boolean; Batalkan: boolean };
    Izin: IzinPersediaan;
    KesiapanAkun: KesiapanAkun;
    BatasPostingLangsung: number;
};
/** GET /kelola/persediaan/stok-awal/{uuid}/status */
export type StatusPostingStokAwal = {
    Status: StatusStokAwal;
    LabelStatus: string;
    Nomor: string | null;
    PesanGalat: string | null;
};
/** GET /kelola/persediaan/produk/cari */
export type HasilCariProdukStok = {
    Data: {
        Uuid: string;
        Nama: string;
        Sku: string | null;
        Jenis: string;
        Pelacakan: PelacakanProduk;
        SimbolSatuan: string;
        BolehDesimal: boolean;
        SaldoDiGudang: string | null;
        HppRataRata: string | null;
        StokAwalSudahAda: boolean;
    }[];
};

export type KeadaanSaldo = 'Semua' | 'Ada' | 'Nol' | 'Minus';
export type BarisSaldoStok = {
    UuidProduk: string;
    NamaProduk: string;
    Sku: string | null;
    SimbolSatuan: string;
    Pelacakan: PelacakanProduk;
    UuidGudang: string;
    NamaGudang: string;
    NamaOutlet: string | null;
    GudangAktif: boolean;
    JumlahTersedia: string;
    HppRataRata: string | null;
    NilaiPersediaan: string;
    DiubahPada: string;
    Batch: { NomorBatch: string; TanggalKedaluwarsa: string | null; JumlahSisa: string }[];
    JumlahNomorSeri: number | null;
    TautanKartuStok: string;
};
export type PropsSaldoStok = {
    Saldo: DaftarBerhalaman<BarisSaldoStok>;
    Ringkasan: { TotalNilai: string; JumlahBaris: number; JumlahMinus: number };
    Saring: { Kata: string; UuidGudang: string | null; Keadaan: KeadaanSaldo; Urut: 'Nama' | '-Nilai' | 'Jumlah' };
    OpsiGudang: OpsiGudang[];
    MetodeHpp: MetodeHpp;
};

export type BarisKartuStok = {
    TanggalBisnis: string;
    DicatatPada: string;
    JenisMutasi: string;
    LabelJenisMutasi: string;
    NomorReferensi: string | null;
    TautanReferensi: string | null;
    Masuk: string | null;
    Keluar: string | null;
    HppSatuan: string;
    TotalHpp: string;
    SaldoSetelah: string;
    NilaiSetelah: string;
    NomorBatch: string | null;
    NomorSeri: string | null;
    DicatatOleh: string | null;
};
export type PropsKartuStok = {
    Produk: { Uuid: string; Nama: string; Sku: string | null; SimbolSatuan: string; Pelacakan: PelacakanProduk } | null;
    Gudang: OpsiGudang | null;
    Saring: { UuidProduk: string | null; UuidGudang: string | null; Dari: string; Sampai: string };
    SaldoAwal: { Jumlah: string; Nilai: string } | null;
    SaldoAkhir: { Jumlah: string; Nilai: string } | null;
    Mutasi: DaftarBerhalaman<BarisKartuStok> | null;
    OpsiGudang: OpsiGudang[];
};

export type PropsPengaturanPersediaan = {
    MetodeHpp: MetodeHpp;
    StokBolehMinus: boolean;
    MetodeHppTerkunci: boolean;
    AlasanTerkunci: string | null;
    OpsiMetodeHpp: { Nilai: MetodeHpp; Label: string; Keterangan: string }[];
};

export type StatusImporStokAwal =
    'Diunggah' | 'MenungguPemetaan' | 'Memvalidasi' | 'Pratinjau' | 'Menerapkan' | 'Selesai' | 'Gagal' | 'Dibatalkan';
export type BidangImporStokAwal =
    | 'Sku'
    | 'Barcode'
    | 'NamaProduk'
    | 'Lokasi'
    | 'Jumlah'
    | 'HargaModal'
    | 'NomorBatch'
    | 'TanggalKedaluwarsa'
    | 'NomorSeri';
export type RingkasanImporStokAwal = {
    Uuid: string;
    NamaBerkas: string;
    Status: StatusImporStokAwal;
    LabelStatus: string;
    JumlahBaris: number;
    JumlahValid: number;
    JumlahGalat: number;
    JumlahDokumen: number;
    Progres: number;
    PesanGalat: string | null;
    NamaGudangBawaan: string | null;
    BerkasPernahDiimpor: string | null;
    BolehLanjutkan: boolean;
    DibuatPada: string;
    SelesaiPada: string | null;
    NamaPengguna: string | null;
};
export type PropsDaftarImporStokAwal = {
    Riwayat: DaftarBerhalaman<RingkasanImporStokAwal>;
    OpsiGudang: OpsiGudang[];
    BatasBerkas: { UkuranMaksimalKb: number; MaksimalBaris: number; Ekstensi: string[] };
};
export type PropsDetailImporStokAwal = {
    Impor: RingkasanImporStokAwal;
    Pemetaan: {
        KolomSumber: { Indeks: number; Judul: string; Contoh: string[] }[];
        Bidang: { Kunci: BidangImporStokAwal; Label: string; Wajib: boolean; Keterangan: string }[];
        Pemetaan: Record<BidangImporStokAwal, number | null>;
        UuidGudangBawaan: string | null;
        Tanggal: string;
    } | null;
    Pratinjau: {
        BarisGalat: { NomorBaris: number; Galat: { Bidang: string; Pesan: string }[]; Data: Record<string, string> }[];
        RingkasanDokumen: { NamaGudang: string; JumlahBaris: number; TotalNilai: string }[];
        Peringatan: string[];
    } | null;
    Dokumen: { Uuid: string; NamaGudang: string; JumlahBaris: number; Status: StatusStokAwal }[];
    OpsiGudang: OpsiGudang[];
};

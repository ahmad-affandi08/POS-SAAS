import type { HasilTabel } from '@/Komponen/TabelData/Tipe';
/**
 * Kontrak props halaman F-03 Master Produk, Harga & Pajak (DesainF03 bagian E, dibekukan di Wave 0).
 *
 * Uang, kuantitas, dan persen SELALU string desimal (CLAUDE.md #7). Props tiap halaman adalah
 * `PropsBersamaAplikasi & {…}`; tipe di bawah adalah bagian `{…}`-nya. Perubahan bentuk = versi kontrak baru.
 */
import type { Batas, Pilihan } from '@/Tipe/Organisasi';

// E.1 Tipe bersama.
export type JenisProduk =
    'Stok' | 'IndukVarian' | 'Resep' | 'Produksi' | 'Paket' | 'Jasa' | 'NonStok' | 'BahanBaku' | 'Konsinyasi';
export type PelacakanProduk = 'Tidak' | 'Batch' | 'Seri';
export type StatusProduk = 'Aktif' | 'Diarsipkan';
export type KategoriPajakProduk = 'KenaPpn' | 'BebasPpn' | 'KenaPbjt' | 'NonPajak' | 'Lainnya';
export type KanalPenjualan = 'MakanDiTempat' | 'BawaPulang' | 'Antar' | 'Online' | 'PesanSendiri' | 'Marketplace';
export type TigaKeadaan = 'Ikut' | 'Ya' | 'Tidak';
export type AturanJenisProduk = {
    Nilai: JenisProduk;
    Label: string;
    PunyaStok: boolean;
    BisaDijual: boolean;
    BolehPelacakan: boolean;
    BolehResep: boolean;
    BolehKomponen: boolean;
    BolehPilihan: boolean;
    DihitungBatasSku: boolean;
};
export type TabProduk = {
    Kunci: 'Ringkasan' | 'Harga' | 'Pilihan' | 'Resep' | 'Komponen';
    Label: string;
    Tautan: string;
};
export type KepalaProduk = {
    Uuid: string;
    Nama: string;
    Sku: string | null;
    Jenis: JenisProduk;
    LabelJenis: string;
    Status: StatusProduk;
    UrlGambarKecil: string | null;
    UuidInduk: string | null;
    NamaInduk: string | null;
    Tab: TabProduk[];
};
export type OpsiKategori = {
    Uuid: string;
    Nama: string;
    /** Misal "Minuman › Kopi". */
    Jalur: string;
    Kedalaman: number;
    UuidInduk: string | null;
};
export type OpsiSatuan = { Uuid: string; Nama: string; Simbol: string; BolehDesimal: boolean };
export type OpsiKelompokPajak = {
    Uuid: string;
    Nama: string;
    Kategori: KategoriPajakProduk | null;
    LabelKategori: string;
};
export type BarisHarga = { JumlahMinimum: string; Harga: string };
export type IzinKatalog = { Kelola: boolean; UbahHarga: boolean; KelolaPersediaan: boolean; KelolaPajak: boolean };

// E.2 Kelola/Produk/Daftar.
export type BarisProduk = {
    Uuid: string;
    Nama: string;
    Sku: string | null;
    Jenis: JenisProduk;
    LabelJenis: string;
    NamaKategori: string | null;
    Merek: string | null;
    HargaDasar: string | null;
    SimbolSatuan: string;
    JumlahVarian: number;
    TampilDiPos: boolean;
    DiubahPada: string | null;
    Status: StatusProduk;
    UrlGambarKecil: string | null;
};
export type PropsDaftarProduk = {
    Produk: HasilTabel<BarisProduk>;
    Kategori: OpsiKategori[];
    Jenis: AturanJenisProduk[];
    BatasSku: Batas;
    Izin: IzinKatalog;
};

// E.3 Kelola/Produk/Form (buat/ubah).
export type FormSatuanProduk = {
    Uuid: string | null;
    UuidSatuan: string;
    KonversiKeDasar: string;
    DefaultJual: boolean;
    DefaultBeli: boolean;
    Barcode: string[];
    /** Hanya untuk satuan baru; [] = tanpa harga. */
    HargaAwal: BarisHarga[];
};
export type FormProduk = {
    Uuid: string;
    Nama: string;
    NamaStruk: string;
    Sku: string;
    Jenis: JenisProduk;
    UuidKategori: string | null;
    Merek: string;
    UuidSatuanDasar: string;
    Pelacakan: PelacakanProduk;
    UuidKelompokPajak: string | null;
    HargaTermasukPajak: TigaKeadaan;
    BolehMinus: TigaKeadaan;
    TampilDiPos: boolean;
    TampilOnline: boolean;
    Satuan: FormSatuanProduk[];
    AtributVarian: { Nama: string; Nilai: string[] }[];
};
export type PropsFormProduk = {
    Mode: 'Buat' | 'Ubah';
    /** Buat: Uuid = ULID baru dari server (kunci idempotensi). */
    Produk: FormProduk;
    Kepala: KepalaProduk | null;
    Kategori: OpsiKategori[];
    Satuan: OpsiSatuan[];
    KelompokPajak: OpsiKelompokPajak[];
    Jenis: AturanJenisProduk[];
    JenisTerkunci: boolean;
    BatasSku: Batas;
    /** HargaTermasukPajakOutlet adalah label. */
    Pengaturan: { HargaTermasukPajakOutlet: string; StokBolehMinus: boolean };
    Izin: IzinKatalog;
};

// E.4 Kelola/Produk/Detail.
export type SatuanDetail = {
    Uuid: string;
    Nama: string;
    Simbol: string;
    KonversiKeDasar: string;
    DefaultJual: boolean;
    DefaultBeli: boolean;
    BisaDijual: boolean;
    Barcode: { Uuid: string; Barcode: string }[];
};
export type BarisVarian = {
    Uuid: string;
    Nama: string;
    Sku: string | null;
    Atribut: { Nama: string; Nilai: string }[];
    HargaDasar: string | null;
    Status: StatusProduk;
};
export type BarisBatasStok = {
    UuidGudang: string;
    NamaGudang: string;
    NamaOutlet: string;
    StokMinimum: string;
    /** "" = kosong. */
    StokMaksimum: string;
};
export type DetailProduk = {
    Uuid: string;
    Nama: string;
    NamaStruk: string | null;
    Sku: string | null;
    Jenis: JenisProduk;
    LabelJenis: string;
    NamaKategori: string | null;
    Merek: string | null;
    SatuanDasar: OpsiSatuan;
    Pelacakan: PelacakanProduk;
    LabelPelacakan: string;
    KelompokPajak: OpsiKelompokPajak | null;
    HargaTermasukPajak: TigaKeadaan;
    BolehMinus: TigaKeadaan;
    TampilDiPos: boolean;
    TampilOnline: boolean;
    UrlGambar: string | null;
    UrlGambarKecil: string | null;
    Satuan: SatuanDetail[];
    AtributVarian: { Nama: string; Nilai: string[] }[];
    AlasanTidakBisaDihapus: string | null;
    DibuatPada: string;
    DiubahPada: string;
    DiarsipkanPada: string | null;
};
export type PropsDetailProduk = {
    Kepala: KepalaProduk;
    Produk: DetailProduk;
    Varian: BarisVarian[];
    /** null = bukan jenis yang punya stok. */
    BatasStok: BarisBatasStok[] | null;
    Riwayat: { Peristiwa: string; NamaPengguna: string | null; DibuatPada: string }[];
    Jenis: AturanJenisProduk[];
    BatasSku: Batas;
    Izin: IzinKatalog;
};

// E.5 Kelola/Kategori/Daftar dan Kelola/Satuan/Daftar.
export type PropsDaftarKategori = {
    Kategori: (OpsiKategori & { JumlahProduk: number; Urutan: number })[];
    Izin: IzinKatalog;
};
// Form: { Nama: string; UuidInduk: string|null; Urutan: string } → POST /kelola/kategori, PUT/DELETE /kelola/kategori/{uuid}.
export type PropsDaftarSatuan = {
    Satuan: (OpsiSatuan & { KodeStandar: string | null; JumlahProduk: number })[];
    Izin: IzinKatalog;
};
// Form: { Nama: string; Simbol: string; BolehDesimal: boolean } → POST /kelola/satuan, PUT/DELETE /kelola/satuan/{uuid}.

// E.6 Kelola/Produk/Harga.
export type BarisRiwayatHarga = {
    DibuatPada: string;
    NamaSatuan: string;
    NamaDaftarHarga: string | null;
    JumlahMinimum: string;
    HargaLama: string | null;
    HargaBaru: string | null;
    NamaPengubah: string | null;
    Sumber: string;
    LabelSumber: string;
};
export type PropsHargaProduk = {
    Kepala: KepalaProduk;
    Satuan: {
        UuidProdukSatuan: string;
        Nama: string;
        Simbol: string;
        KonversiKeDasar: string;
        BolehDesimal: boolean;
        HargaDasar: BarisHarga[];
    }[];
    DaftarHarga: {
        Uuid: string;
        Nama: string;
        Aktif: boolean;
        Ringkasan: string;
        Harga: (BarisHarga & { UuidProdukSatuan: string })[];
    }[];
    Riwayat: HasilTabel<BarisRiwayatHarga>;
    OpsiSumberRiwayat: Pilihan[];
    LabelHargaTermasukPajak: string;
    Izin: IzinKatalog;
};

// E.7 Kelola/DaftarHarga/Daftar dan /Detail.
export type FormDaftarHarga = {
    Nama: string;
    /** [] = semua outlet. */
    UuidOutlet: string[];
    Kanal: KanalPenjualan | '';
    TierPelanggan: string;
    MulaiPada: string;
    /** 'YYYY-MM-DDTHH:mm' di zona waktu tenant, '' = kosong (berlaku juga untuk MulaiPada). */
    SelesaiPada: string;
    Prioritas: string;
};
export type BarisDaftarHarga = {
    Uuid: string;
    Nama: string;
    NamaOutlet: string[] | null;
    Kanal: KanalPenjualan | null;
    LabelKanal: string | null;
    TierPelanggan: string | null;
    MulaiPada: string | null;
    SelesaiPada: string | null;
    Prioritas: number;
    Aktif: boolean;
    JumlahProduk: number;
};
export type PropsDaftarDaftarHarga = {
    DaftarHarga: HasilTabel<BarisDaftarHarga>;
    Outlet: Pilihan[];
    Kanal: Pilihan[];
    ZonaWaktu: string;
    Izin: IzinKatalog;
};
export type PropsDetailDaftarHarga = {
    DaftarHarga: FormDaftarHarga & { Uuid: string; Aktif: boolean };
    Baris: HasilTabel<{
        UuidProduk: string;
        NamaProduk: string;
        Sku: string | null;
        UuidProdukSatuan: string;
        NamaSatuan: string;
        HargaDasar: string | null;
        Harga: BarisHarga[];
    }>;
    Outlet: Pilihan[];
    Kanal: Pilihan[];
    ZonaWaktu: string;
    Izin: IzinKatalog;
};

// E.8 Kelola/KelompokPajak/Daftar.
export type PropsDaftarKelompokPajak = {
    KelompokPajak: (OpsiKelompokPajak & {
        Pajak: {
            KodeJenisPajak: string;
            NamaJenisPajak: string;
            DasarPengenaan: string;
            LabelDasarPengenaan: string;
        }[];
        JumlahProduk: number;
    })[];
    JenisPajak: { Kode: string; Nama: string; Cakupan: string }[];
    Kategori: Pilihan[];
    DasarPengenaan: Pilihan[];
    Izin: IzinKatalog;
};
// Form { Nama; Kategori: KategoriPajakProduk; Pajak: { KodeJenisPajak; DasarPengenaan }[] } → POST /kelola/kelompok-pajak, PUT /kelola/kelompok-pajak/{uuid}.

// E.9 Halaman Tim 3.
export type FormPilihan = {
    Uuid: string | null;
    Nama: string;
    Harga: string;
    Aktif: boolean;
    UuidProdukBahan: string | null;
    Jumlah: string;
};
export type FormKelompokPilihan = {
    Nama: string;
    MinimalPilih: string;
    MaksimalPilih: string;
    Urutan: string;
    Pilihan: FormPilihan[];
};
export type PropsDaftarKelompokPilihan = {
    KelompokPilihan: (FormKelompokPilihan & {
        Uuid: string;
        Wajib: boolean;
        JumlahProduk: number;
        Pilihan: (FormPilihan & { NamaProdukBahan: string | null; SimbolSatuanBahan: string | null })[];
    })[];
    Izin: IzinKatalog;
};
export type PropsPilihanProduk = {
    Kepala: KepalaProduk;
    Terpasang: { Uuid: string; Nama: string; Ringkasan: string }[];
    Tersedia: { Uuid: string; Nama: string; Ringkasan: string }[];
    DariInduk: boolean;
    Izin: IzinKatalog;
};
export type BahanResep = {
    UuidProdukBahan: string;
    NamaBahan: string;
    Sku: string | null;
    Jumlah: string;
    UuidSatuan: string;
    SimbolSatuan: string;
    JumlahDasar: string;
    SimbolSatuanDasar: string;
    PersenSusut: string;
};
export type PropsResepProduk = {
    Kepala: KepalaProduk;
    Resep: {
        Versi: number;
        JumlahHasil: string;
        SimbolSatuanHasil: string;
        Catatan: string | null;
        DibuatPada: string;
        NamaPembuat: string | null;
        Bahan: BahanResep[];
    } | null;
    VersiTerbaru: number | null;
    DaftarVersi: { Versi: number; DibuatPada: string; NamaPembuat: string | null }[];
    Hpp: {
        Status: 'Tersedia' | 'BelumTersedia' | 'TanpaResep';
        HppSatuan: string | null;
        Baris: { NamaBahan: string; JumlahKotor: string; HppSatuanBahan: string | null; Subtotal: string | null }[];
    };
    Izin: IzinKatalog;
};
export type PropsKomponenProduk = {
    Kepala: KepalaProduk;
    Komponen: {
        UuidProdukKomponen: string;
        Nama: string;
        Sku: string | null;
        Jumlah: string;
        SimbolSatuan: string;
        /** "" = otomatis. */
        AlokasiHarga: string;
    }[];
    Izin: IzinKatalog;
};

// E.10 Halaman Tim 4.
export type StatusImporProduk =
    'Diunggah' | 'MenungguPemetaan' | 'Memvalidasi' | 'Pratinjau' | 'Menerapkan' | 'Selesai' | 'Gagal' | 'Dibatalkan';
/** Kunci dari daftar Backend (DesainF03 C.6). */
export type BidangImpor = string;
export type OpsiImpor = {
    Mode: 'TambahSaja' | 'TambahDanPerbarui';
    UuidKelompokPajakBawaan: string | null;
    JenisBawaan: JenisProduk;
    BuatKategoriBaru: boolean;
    BuatSatuanBaru: boolean;
};
export type RingkasanImpor = {
    Uuid: string;
    NamaBerkas: string;
    Sumber: string;
    LabelSumber: string;
    Status: StatusImporProduk;
    LabelStatus: string;
    JumlahBaris: number;
    JumlahValid: number;
    JumlahGalat: number;
    JumlahDiterapkan: number;
    JumlahDibuat: number;
    JumlahDiperbarui: number;
    JumlahDilewati: number;
    JumlahGagal: number;
    Progres: number;
    PesanGalat: string | null;
    /** Tanggal ISO. */
    BerkasPernahDiimpor: string | null;
    BolehLanjutkan: boolean;
    DibuatPada: string;
    SelesaiPada: string | null;
    NamaPengguna: string | null;
};
export type PropsDaftarImpor = {
    Riwayat: HasilTabel<RingkasanImpor>;
    OpsiStatus: { Nilai: StatusImporProduk; Label: string }[];
    Preset: { Kode: string; Nama: string; Keterangan: string; Asumsi: boolean }[];
    BatasBerkas: { UkuranMaksimalKb: number; MaksimalBaris: number; Ekstensi: string[] };
    BatasSku: Batas;
    Izin: IzinKatalog;
};
export type PropsDetailImpor = {
    Impor: RingkasanImpor;
    Pemetaan: {
        KolomSumber: { Indeks: number; Judul: string; Contoh: string[] }[];
        Bidang: { Kunci: BidangImpor; Label: string; Wajib: boolean; Harga: boolean; Keterangan: string }[];
        Pemetaan: Record<BidangImpor, number | null>;
        Opsi: OpsiImpor;
    } | null;
    Pratinjau: {
        BarisGalat: { NomorBaris: number; Galat: { Bidang: string; Pesan: string }[]; Data: Record<string, string> }[];
        RingkasanAksi: { Buat: number; Perbarui: number; Lewati: number };
        Peringatan: string[];
        DiblokirBatasSku: string | null;
    } | null;
    KelompokPajak: OpsiKelompokPajak[];
    Jenis: AturanJenisProduk[];
    Izin: IzinKatalog;
};

// D.2 Respons JSON sesi (dibaca lewat TanStack Query; cermin kontrak Backend, bukan props halaman).
/** GET /kelola/produk/cari?kata=&jenis[]=&batas= */
export type HasilCariProduk = {
    Data: {
        Uuid: string;
        Nama: string;
        Sku: string | null;
        Jenis: JenisProduk;
        UuidSatuanDasar: string;
        Satuan: { Uuid: string; Nama: string; Simbol: string; BolehDesimal: boolean; KonversiKeDasar: string }[];
    }[];
};
/** GET /kelola/produk/impor/{uuid}/status */
export type StatusImpor = {
    Status: StatusImporProduk;
    LabelStatus: string;
    Progres: number;
    JumlahDiterapkan: number;
    JumlahGagal: number;
    PesanGalat: string | null;
};

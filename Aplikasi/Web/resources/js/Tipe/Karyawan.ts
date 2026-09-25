import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** Kontrak props halaman karyawan F-18 bagian 1 (karyawan, jadwal kerja, absensi). Uang string desimal; jam `HH:mm`. */

export type StatusKaryawan = 'Aktif' | 'Nonaktif';
export type OpsiUuidNama = { Uuid: string; Nama: string };

export type BarisKaryawan = {
    Uuid: string;
    Nama: string;
    Jabatan: string | null;
    LevelStaf: string | null;
    /** Hanya untuk pelihat ber-izin `karyawan.kelola`. */
    GajiPokok: string | null;
    UuidPengguna: string | null;
    NamaPengguna: string | null;
    UuidOutlet: string | null;
    NamaOutlet: string | null;
    Status: StatusKaryawan;
    LabelStatus: string;
};

export type PropsDaftarKaryawan = {
    Karyawan: HasilTabel<BarisKaryawan>;
    OpsiPengguna: OpsiUuidNama[];
    OpsiOutlet: OpsiUuidNama[];
    Izin: { Kelola: boolean };
};

/** Halaman penuh "Tambah karyawan" (`/kelola/karyawan/buat`). */
export type PropsBuatKaryawan = Pick<PropsDaftarKaryawan, 'OpsiPengguna' | 'OpsiOutlet'>;

export type SelJadwal = { JamMulai: string; JamSelesai: string; OutletLain: boolean } | null;

export type BarisJadwal = {
    Uuid: string;
    Nama: string;
    Jabatan: string | null;
    Aktif: boolean;
    Jadwal: Record<string, SelJadwal>;
};

export type PropsJadwalKerja = {
    OpsiOutlet: OpsiUuidNama[];
    UuidOutlet: string | null;
    Senin: string;
    Jadwal: { Hari: string[]; Baris: BarisJadwal[] };
    Izin: { Kelola: boolean };
};

export type StatusKehadiran = 'TepatWaktu' | 'Terlambat' | 'TanpaJadwal' | 'BelumKeluar';

export type BarisAbsensi = {
    Uuid: string;
    TanggalBisnis: string;
    UuidKaryawan: string | null;
    NamaKaryawan: string | null;
    NamaOutlet: string | null;
    JamMasuk: string;
    JamKeluar: string | null;
    KeluarBeda: boolean;
    DurasiMenit: number | null;
    Jadwal: string | null;
    TerlambatMenit: number;
    Status: StatusKehadiran;
    LabelStatus: string;
    AdaSwafotoMasuk: boolean;
    AdaSwafotoKeluar: boolean;
};

export type PropsAbsensi = {
    Absensi: HasilTabel<BarisAbsensi>;
    OpsiKaryawan: OpsiUuidNama[];
    OpsiOutlet: OpsiUuidNama[];
};

/* F-18 bagian 2: komisi. */
export type CakupanKomisi = 'Semua' | 'Kategori' | 'Produk';
export type JenisKomisi = 'Persen' | 'Tetap';

export type BarisAturanKomisi = {
    Uuid: string;
    Nama: string;
    Cakupan: CakupanKomisi;
    LabelCakupan: string;
    UuidProduk: string | null;
    UuidKategori: string | null;
    NamaSasaran: string;
    LevelStaf: string | null;
    Jenis: JenisKomisi;
    Nilai: string;
    Status: 'Aktif' | 'Diarsipkan';
};

export type PropsAturanKomisi = {
    Aturan: BarisAturanKomisi[];
    OpsiKategori: OpsiUuidNama[];
    Izin: { Kelola: boolean };
};

/** Halaman penuh "Tambah aturan komisi" (`/kelola/karyawan/komisi/buat`). */
export type PropsBuatAturanKomisi = Pick<PropsAturanKomisi, 'OpsiKategori'>;

export type BarisLaporanKomisi = {
    Uuid: string;
    Nama: string;
    Jabatan: string | null;
    JumlahBaris: number;
    TotalDasar: string;
    Kotor: string;
    Dibatalkan: string;
    Bersih: string;
};

export type PropsLaporanKomisi = {
    Komisi: HasilTabel<BarisLaporanKomisi, { Bersih: string }> & { Ringkasan: { Bersih: string } };
    OpsiOutlet: OpsiUuidNama[];
};

/** F-18 bagian 3: kasbon karyawan. */
export type StatusKasbon = 'Aktif' | 'Lunas' | 'Dibatalkan';

export type BarisKasbon = {
    Uuid: string;
    Karyawan: string;
    UuidKaryawan: string | null;
    Tanggal: string;
    Jumlah: string;
    Sisa: string;
    Status: StatusKasbon;
    LabelStatus: string;
    Keterangan: string | null;
    AkunKasBank: string;
    AlasanBatal: string | null;
};

export type PropsKasbon = {
    Kasbon: HasilTabel<BarisKasbon>;
    TotalSisa: string;
    OpsiKaryawan: OpsiUuidNama[];
    OpsiAkunKasBank: OpsiUuidNama[];
    Izin: { Kelola: boolean };
};

/** F-18 bagian 3: rekap gaji bulanan. Uang = string desimal. */
export type StatusRekapGaji = 'Draf' | 'Dibayar';

export type BarisRekapGaji = {
    Uuid: string;
    Periode: string;
    LabelPeriode: string;
    Status: StatusRekapGaji;
    LabelStatus: string;
    TotalKotor: string;
    TotalPotongan: string;
    TotalBersih: string;
    TanggalBayar: string | null;
};

export type PropsDaftarRekapGaji = {
    Rekap: HasilTabel<BarisRekapGaji>;
    OpsiPeriode: { Nilai: string; Label: string }[];
    OpsiStatus: { Nilai: StatusRekapGaji; Label: string }[];
};

export type BarisGajiKaryawan = {
    UuidKaryawan: string;
    Nama: string;
    Jabatan: string | null;
    GajiPokok: string;
    Komisi: string;
    Tambahan: string;
    Kotor: string;
    PotonganKasbon: string;
    PotonganLain: string;
    Bersih: string;
    Catatan: string | null;
    /** Sisa kasbon aktif saat ini: batas potongan kasbon selama draf. */
    SisaKasbon: string;
};

export type OpsiAkunGaji = { Uuid: string; Kode: string; Nama: string };

export type PropsDetailRekapGaji = {
    Rekap: BarisRekapGaji & {
        AkunKasBank: string | null;
        AkunBeban: string | null;
        Jurnal: { Uuid: string; Nomor: string } | null;
    };
    Baris: BarisGajiKaryawan[];
    OpsiAkunKasBank: OpsiAkunGaji[];
    OpsiAkunBeban: OpsiAkunGaji[];
};

/** F-18 bagian 3: target penjualan bulanan per outlet/karyawan + progres. */
export type CakupanTargetPenjualan = 'Outlet' | 'Karyawan';

export type BarisTargetPenjualan = {
    Uuid: string;
    Cakupan: CakupanTargetPenjualan;
    LabelCakupan: string;
    UuidSasaran: string;
    NamaSasaran: string;
    Nilai: string;
    Realisasi: string;
    /** Persen realisasi ÷ target, 1 desimal ("82.5"); bisa > 100. */
    Persen: string;
    Sisa: string;
    /** Hanya bulan berjalan: realisasi ÷ hari berlalu × jumlah hari. */
    Proyeksi: string | null;
};

export type PropsTargetPenjualan = {
    Periode: string;
    Berjalan: boolean;
    OpsiPeriode: { Nilai: string; Label: string }[];
    Target: BarisTargetPenjualan[];
    OpsiOutlet: OpsiUuidNama[];
    OpsiKaryawan: OpsiUuidNama[];
    Izin: { Kelola: boolean };
};

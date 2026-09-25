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

import type { HasilTabel } from '@/Komponen/TabelData/Tipe';
import type { PenjualanShift } from '@/Tipe/Penjualan';

/** F-06 shift & kas (back-office). Uang = string desimal dari server; tidak pernah number/float. */

export type StatusShift = 'Terbuka' | 'Menutup' | 'Tertutup' | 'DibukaUlang';
export type JenisMutasiKas = 'Masuk' | 'Keluar' | 'Setoran';
export type JenisKategoriKas = 'Masuk' | 'Keluar';

export type RingkasanKasShift = {
    TotalMasuk: string;
    TotalKeluar: string;
    TotalSetoran: string;
    /** Kas awal + masuk − keluar − setoran; penjualan tunai ditambahkan F-07. */
    KasNonPenjualan: string;
};

export type BarisShift = RingkasanKasShift & {
    Uuid: string;
    NamaOutlet: string;
    Perangkat: string;
    NamaKasir: string;
    DibukaPada: string;
    TanggalBisnis: string;
    Status: StatusShift;
    LabelStatus: string;
    Bersama: boolean;
    PerluTinjauan: boolean;
    KasAwal: string;
};

export type PropsDaftarShift = {
    Shift: HasilTabel<BarisShift>;
    OpsiOutlet: { Uuid: string; Nama: string }[];
    OpsiStatus: { Nilai: StatusShift; Label: string }[];
};

export type PecahanKas = { Nominal: string; Jumlah: number };

export type BarisMutasiKas = {
    Uuid: string;
    Jenis: JenisMutasiKas;
    LabelJenis: string;
    NamaKategori: string | null;
    Jumlah: string;
    Catatan: string | null;
    DicatatOleh: string;
    DicatatPada: string;
    DisetujuiOleh: string | null;
    NomorJurnal: string | null;
    UuidJurnal: string | null;
};

export type PropsDetailShift = {
    Shift: Omit<BarisShift, 'Uuid'> & {
        Uuid: string;
        DiterimaPada: string;
        AlasanTinjauan: string | null;
        PecahanKasAwal: PecahanKas[];
    };
    MutasiKas: BarisMutasiKas[];
    /** F-07b: penjualan yang dibuat di shift ini. */
    Penjualan: PenjualanShift;
};

export type OpsiAkun = { Uuid: string; Kode: string; Nama: string; Jenis: string };

export type BarisKategoriKas = {
    Uuid: string;
    Nama: string;
    Jenis: JenisKategoriKas;
    LabelJenis: string;
    UuidAkun: string | null;
    Akun: string | null;
    Aktif: boolean;
    Urutan: number;
};

export type PropsKategoriKas = {
    Kategori: BarisKategoriKas[];
    OpsiAkun: Record<JenisKategoriKas, OpsiAkun[]>;
};

export type PembulatanTunai = { Kelipatan: number; Arah: string };

export type PropsPengaturanKasir = {
    BatasKasKeluar: string;
    ShiftBersama: boolean;
    /** F-07b BR-07.3: persen string desimal ("10.00"). */
    BatasDiskonManual: string;
    BatasDiskonPenyetuju: string;
    /** F-07b BR-08.6: null = tanpa pembulatan tunai. */
    PembulatanTunai: PembulatanTunai | null;
    OpsiArahPembulatan: { Nilai: string; Label: string }[];
};

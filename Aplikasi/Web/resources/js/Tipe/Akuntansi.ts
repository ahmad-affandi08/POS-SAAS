import type { HasilTabel } from '@/Komponen/TabelData/Tipe';
/**
 * Kontrak props halaman jurnal F-05a (baca saja, DesainF05a E). Uang = string desimal ("12345.68"), tanggal
 * `YYYY-MM-DD`, cap waktu ISO UTC. Pemilik file: Tim 0 (perubahan lewat permintaan ke lead).
 */
import type { Pilihan } from '@/Tipe/Pengelola';

export type BarisDaftarJurnal = {
    Uuid: string;
    Nomor: string;
    Tanggal: string;
    JenisSumber: string;
    LabelJenisSumber: string;
    NomorSumber: string | null;
    TautanSumber: string | null;
    Keterangan: string;
    TotalDebit: string;
    Otomatis: boolean;
    Dibalik: boolean;
    Pembalik: boolean;
};
export type PropsDaftarJurnal = {
    Jurnal: HasilTabel<BarisDaftarJurnal>;
    OpsiJenisSumber: Pilihan[];
};
export type PropsDetailJurnal = {
    Jurnal: BarisDaftarJurnal & {
        Periode: string;
        DibuatOleh: string | null;
        DibuatPada: string;
        UuidJurnalDibalik: string | null;
        NomorJurnalDibalik: string | null;
        UuidPembalik: string | null;
        NomorPembalik: string | null;
    };
    Baris: {
        Urutan: number;
        KodeAkun: string;
        NamaAkun: string;
        NamaOutlet: string | null;
        Debit: string;
        Kredit: string;
        Memo: string | null;
    }[];
    Total: { Debit: string; Kredit: string };
};

/*
 * F-13a (PRD "Rincian F-13a"): bagan akun, pemetaan akun, transaksi kas & bank, laporan keuangan. Uang = string
 * desimal, tanggal `YYYY-MM-DD`.
 */
export type TipeAkun = 'Aset' | 'Kewajiban' | 'Ekuitas' | 'Pendapatan' | 'Hpp' | 'Beban';

export type BarisBaganAkun = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Jenis: TipeAkun;
    LabelJenis: string;
    SaldoNormal: 'Debit' | 'Kredit';
    Kontra: boolean;
    KasBank: boolean;
    Aktif: boolean;
    Sistem: boolean;
    Kedalaman: number;
    UuidInduk: string | null;
    KodeInduk: string | null;
    AdaJurnal: boolean;
    PeranDipetakan: string[];
    PunyaAnak: boolean;
    BisaDihapus: boolean;
};
export type PropsBaganAkun = {
    Akun: BarisBaganAkun[];
    OpsiTipe: { Nilai: TipeAkun; Label: string; DigitAwal: string }[];
    Izin: { Kelola: boolean };
};

export type StatusPemetaanAkun = 'Sesuai' | 'TipeSalah' | 'BelumDipetakan' | 'AkunNonaktif';
export type BarisPemetaanAkun = {
    Id: string;
    Kunci: string;
    LabelPeran: string;
    TipeWajib: TipeAkun;
    LabelTipeWajib: string;
    WajibKontra: boolean;
    UuidOutlet: string | null;
    NamaOutlet: string | null;
    UuidAkun: string | null;
    KodeAkun: string | null;
    NamaAkun: string | null;
    Status: StatusPemetaanAkun;
    PesanStatus: string | null;
};
export type PropsPemetaanAkun = {
    Pemetaan: BarisPemetaanAkun[];
    OpsiAkun: { Uuid: string; Kode: string; Nama: string; Jenis: TipeAkun; Kontra: boolean }[];
    OpsiOutlet: { Uuid: string; Nama: string }[];
    Izin: { Kelola: boolean; UbahSemuaOutlet: boolean };
};

export type JenisTransaksiKasBank = 'Pengeluaran' | 'Penerimaan' | 'Transfer';
export type BarisTransaksiKasBank = {
    Uuid: string;
    Nomor: string;
    Tanggal: string;
    Jenis: JenisTransaksiKasBank;
    LabelJenis: string;
    NamaOutlet: string | null;
    AkunSumber: string;
    AkunTujuan: string;
    Jumlah: string;
    Keterangan: string;
    Pembalik: boolean;
    AdaLampiran: boolean;
    Dibalik: boolean;
};
export type BarisSaldoKasBank = { Uuid: string; Kode: string; Nama: string; Aktif: boolean; Saldo: string };
export type OpsiAkunKasBank = { Uuid: string; Kode: string; Nama: string; Jenis: TipeAkun; KasBank: boolean };
export type PropsDaftarTransaksiKasBank = {
    Transaksi: HasilTabel<BarisTransaksiKasBank>;
    Saldo: BarisSaldoKasBank[];
    OpsiJenis: Pilihan[];
    OpsiOutlet: { Uuid: string; Nama: string }[];
    OpsiAkun: OpsiAkunKasBank[];
    WajibOutlet: boolean;
    Lampiran: { Ekstensi: string[]; UkuranMaksimalKb: number };
    Izin: { Kelola: boolean };
};
export type PropsDetailTransaksiKasBank = {
    Transaksi: BarisTransaksiKasBank & {
        DibuatOleh: string | null;
        DibuatPada: string;
        UuidDibalik: string | null;
        NomorDibalik: string | null;
        UuidPembalik: string | null;
        NomorPembalik: string | null;
        Lampiran: { Nama: string; Ukuran: number } | null;
    };
    Jurnal: {
        Uuid: string;
        Nomor: string;
        Tanggal: string;
        Keterangan: string;
        TotalDebit: string;
        Pembalik: boolean;
    }[];
    Izin: { Kelola: boolean };
};

export type SaringLaporanKeuangan = { Dari: string; Sampai: string; Outlet: string };
export type BarisBukuBesar = {
    Id: string;
    Tanggal: string;
    UuidJurnal: string;
    NomorJurnal: string;
    Keterangan: string;
    Memo: string | null;
    LabelSumber: string;
    NomorSumber: string | null;
    TautanSumber: string | null;
    NamaOutlet: string | null;
    Debit: string;
    Kredit: string;
    Saldo: string;
};
export type RingkasanBukuBesar = { SaldoAwal: string; TotalDebit: string; TotalKredit: string; SaldoAkhir: string };
export type PropsBukuBesar = {
    Saring: SaringLaporanKeuangan & { Akun: string };
    Akun: { Uuid: string; Kode: string; Nama: string; SaldoNormal: 'Debit' | 'Kredit' } | null;
    Mutasi: HasilTabel<BarisBukuBesar, RingkasanBukuBesar>;
    OpsiAkun: Pilihan[];
    OpsiOutlet: { Uuid: string; Nama: string }[];
};
export type KolomNeracaSaldo =
    'SaldoAwalDebit' | 'SaldoAwalKredit' | 'Debit' | 'Kredit' | 'SaldoAkhirDebit' | 'SaldoAkhirKredit';
export type BarisNeracaSaldo = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Jenis: TipeAkun;
    LabelJenis: string;
} & Record<KolomNeracaSaldo, string>;
export type PropsNeracaSaldo = {
    Saring: SaringLaporanKeuangan;
    Laporan: { Baris: BarisNeracaSaldo[]; Total: Record<KolomNeracaSaldo, string>; Seimbang: boolean };
    OpsiOutlet: { Uuid: string; Nama: string }[];
};
export type BarisLabaRugi = {
    Id: string;
    Jenis: 'Kepala' | 'Akun' | 'Subtotal' | 'Laba';
    Kelompok: string;
    Kode: string | null;
    Label: string;
    Nilai: string | null;
    NilaiSebelumnya: string | null;
};
export type NilaiBanding = { Nilai: string; NilaiSebelumnya: string };
export type PropsLabaRugi = {
    Saring: SaringLaporanKeuangan;
    Laporan: {
        Periode: { Dari: string; Sampai: string; DariSebelumnya: string; SampaiSebelumnya: string };
        Baris: BarisLabaRugi[];
        Ringkasan: Record<'Pendapatan' | 'Hpp' | 'LabaKotor' | 'Beban' | 'LabaBersih', NilaiBanding>;
    };
    OpsiOutlet: { Uuid: string; Nama: string }[];
};

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

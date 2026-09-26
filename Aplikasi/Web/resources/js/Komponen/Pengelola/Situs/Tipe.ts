/** Tipe konsol situs pemasaran (D-21). */

export type GambarPustaka = {
    Uuid: string;
    Url: string;
    NamaBerkas: string;
    TeksAlternatif: string | null;
    Lebar: number | null;
    Tinggi: number | null;
    Ukuran: number;
    DibuatPada: string | null;
};

export type TautanMenu = { Label: string; Tautan: string };

/** Aturan bidang dari `SkemaBagianSitus::AmbilSkema()` (tuple PHP). */
export type AturanBidang =
    | ['Teks' | 'TeksPanjang', number, boolean?]
    | ['Tombol']
    | ['Tautan', boolean?]
    | ['Gambar', boolean?]
    | ['Ikon']
    | ['Pilihan', string[]]
    | ['Bilangan', number, number]
    | ['Benar']
    | ['Daftar', number, number, SkemaBlok];

export type SkemaBlok = Record<string, AturanBidang>;

/** Nilai bidang blok (bentuk JSON). */
export type NilaiJson = string | number | boolean | null | NilaiJson[] | { [kunci: string]: NilaiJson };

export type NilaiBlok = { [kunci: string]: NilaiJson };

/** Situs pemasaran (D-21): props dari `PenyusunHalamanSitus`. */

export type TautanSitus = { Label: string; Tautan: string };

export type GambarSitus = {
    Uuid: string;
    Url: string;
    UrlAbsolut?: string;
    Alt: string;
    Lebar: number | null;
    Tinggi: number | null;
};

export type DataSitus = {
    NamaSitus: string;
    Slogan: string | null;
    Logo: GambarSitus | null;
    Menu: TautanSitus[];
    MenuKaki: { Judul: string; Tautan: TautanSitus[] }[];
    TeksKaki: string | null;
    Kontak: {
        WhatsApp: string | null;
        TautanWhatsApp: string | null;
        Email: string | null;
        Telepon: string | null;
        Alamat: string | null;
        JamLayanan: string | null;
    };
    MediaSosial: Partial<Record<'Instagram' | 'Facebook' | 'Tiktok' | 'Youtube' | 'Linkedin' | 'X', string>>;
    Pengumuman: { Teks: string; Tautan: string | null } | null;
    TautanUnduh: Partial<Record<'Android' | 'Ios' | 'Windows', string>>;
    TombolDaftar: TautanSitus;
    TombolMasuk: TautanSitus;
    WhatsAppMelayang: boolean;
    Tahun: number;
};

export type Tombol = { Label: string; Tautan: string } | null;

export type PaketHarga = {
    Kode: string;
    Nama: string;
    Keterangan: string | null;
    HargaNegosiasi: boolean;
    MasaTrialHari: number;
    HargaBulanan: string | null;
    HargaTahunan: string | null;
    HematTahunan: string | null;
    Batas: string[];
    Fitur: string[];
};

type JudulBagian = { Label: string | null; Judul: string | null; Subjudul: string | null };

export type BagianSitus =
    | ({ Jenis: 'Hero' } & {
          Label: string | null;
          Judul: string;
          Subjudul: string | null;
          TombolUtama: Tombol;
          TombolKedua: Tombol;
          Gambar: GambarSitus | null;
          Catatan: string | null;
      })
    | ({ Jenis: 'Keunggulan' } & JudulBagian & {
              Kolom: '2' | '3' | '4' | null;
              Item: { Ikon: string | null; Judul: string; Teks: string | null }[];
          })
    | ({ Jenis: 'Sektor' } & JudulBagian & {
              Item: {
                  Ikon: string | null;
                  Nama: string;
                  Teks: string | null;
                  Tautan: string | null;
                  Gambar: GambarSitus | null;
              }[];
          })
    | ({ Jenis: 'GambarTeks' } & JudulBagian & {
              Teks: string | null;
              Poin: { Teks: string }[];
              Gambar: GambarSitus | null;
              PosisiGambar: 'Kanan' | 'Kiri' | null;
              Tombol: Tombol;
          })
    | ({ Jenis: 'Statistik' } & JudulBagian & { Item: { Angka: string; Keterangan: string }[] })
    | ({ Jenis: 'Testimoni' } & JudulBagian & {
              Item: {
                  Nama: string;
                  Usaha: string | null;
                  Kutipan: string;
                  Foto: GambarSitus | null;
                  Bintang: number | null;
              }[];
          })
    | ({ Jenis: 'Harga' } & JudulBagian & {
              TampilkanTahunan: boolean;
              PaketDisorot: string | null;
              TeksTombol: string | null;
              CatatanKaki: string | null;
              Paket: PaketHarga[];
              TautanDaftar: string;
          })
    | ({ Jenis: 'Faq' } & JudulBagian & { Item: { Pertanyaan: string; Jawaban: string }[] })
    | ({ Jenis: 'Cta' } & { Judul: string; Teks: string | null; TombolUtama: Tombol; TombolKedua: Tombol })
    | ({ Jenis: 'TeksBebas' } & JudulBagian & { Isi: string })
    | ({ Jenis: 'LogoMitra' } & JudulBagian & {
              Item: { Gambar: GambarSitus | null; Nama: string; Tautan: string | null }[];
          })
    | ({ Jenis: 'Video' } & JudulBagian & { UrlYoutube: string; IdYoutube: string | null })
    | ({ Jenis: 'UnduhAplikasi' } & JudulBagian)
    | ({ Jenis: 'Kontak' } & JudulBagian);

export type HalamanSitus = {
    Slug: string;
    Judul: string;
    Bagian: BagianSitus[];
    Seo: { Judul: string; Deskripsi: string };
    Pratinjau?: boolean;
};

export type PropsHalamanSitus = { Halaman: HalamanSitus; Situs: DataSitus };

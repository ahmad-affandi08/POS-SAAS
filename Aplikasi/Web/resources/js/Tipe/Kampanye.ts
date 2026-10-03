import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** CRM-07 kampanye pesan WhatsApp bersegmen (kanal Email hanya untuk riwayat lama, D-33). Waktu = ISO-8601 UTC dari server. */

export type KanalKampanye = 'Whatsapp' | 'Email';
export type StatusKampanye = 'Draf' | 'Dijadwalkan' | 'Berjalan' | 'Selesai' | 'Dibatalkan';
export type StatusPenerimaKampanye = 'Diantrekan' | 'Terkirim' | 'Gagal' | 'Dilewati';
export type SegmenRfm = 'Juara' | 'Setia' | 'Baru' | 'Potensial' | 'Berisiko' | 'Hilang' | 'BelumBelanja';

export type OpsiKampanye = { Nilai: string; Label: string };

export type SegmenKampanye = {
    Rfm?: string[];
    UuidTier?: string[];
    Tag?: string[];
    UlangTahunBulanIni?: boolean;
};

export type BarisKampanye = {
    Uuid: string;
    Nama: string;
    Kanal: KanalKampanye;
    LabelKanal: string;
    Status: StatusKampanye;
    LabelStatus: string;
    DijadwalkanPada: string | null;
    MulaiPada: string | null;
    SelesaiPada: string | null;
    JumlahPenerima: number;
    JumlahTerkirim: number;
    JumlahGagal: number;
    JumlahDilewati: number;
    DibuatPada: string | null;
};

export type PropsDaftarKampanye = {
    Kampanye: HasilTabel<BarisKampanye>;
    OpsiStatus: OpsiKampanye[];
    OpsiKanal: OpsiKampanye[];
};

export type PropsFormKampanye = {
    Kampanye: {
        Uuid: string;
        Nama: string;
        Kanal: KanalKampanye;
        Judul: string | null;
        Isi: string;
        Segmen: SegmenKampanye;
    } | null;
    OpsiKanal: OpsiKampanye[];
    OpsiRfm: (OpsiKampanye & { Keterangan: string })[];
    OpsiTier: OpsiKampanye[];
    OpsiTag: OpsiKampanye[];
    KanalAktif: Record<KanalKampanye, boolean>;
    MaksIsi: number;
};

export type PratinjauKampanye = {
    JumlahPenerima: number;
    TanpaKontak: number;
    PerSegmen: Record<SegmenRfm, number>;
    MaksPenerima: number;
};

export type BarisPenerimaKampanye = {
    Kunci: string;
    UuidPelanggan: string | null;
    NamaPelanggan: string;
    Status: StatusPenerimaKampanye;
    LabelStatus: string;
    PesanGalat: string | null;
    TerkirimPada: string | null;
};

export type PropsDetailKampanye = {
    Kampanye: BarisKampanye & {
        Judul: string | null;
        Isi: string;
        Contoh: string;
        Segmen: string[];
    };
    Penerima: HasilTabel<BarisPenerimaKampanye>;
    OpsiStatusPenerima: OpsiKampanye[];
    Aturan: { JamMulai: number; JamSelesai: number; UkuranGiliran: number; JedaDetik: number };
};

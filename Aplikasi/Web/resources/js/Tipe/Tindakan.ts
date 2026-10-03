/** D-23 C: Kotak Tindakan. */
export type TingkatTindakan = 'Penting' | 'Perhatian' | 'Info';

export type RincianTindakan = {
    Uuid: string;
    Judul: string;
    Keterangan: string | null;
    Tanggal: string | null;
    Tautan: string | null;
};

export type ButirTindakan = {
    Kunci: string;
    Modul: string;
    Tingkat: TingkatTindakan;
    Judul: string;
    Keterangan: string;
    Jumlah: number;
    Tautan: string;
    LabelTautan: string;
    JenisDokumen: string | null;
    BolehTandai: boolean;
    Rincian: RincianTindakan[];
};

/** D-23 D: pilihan pribadi menerima ringkasan pagi lewat WhatsApp. */
export type RingkasanWhatsappTindakan = { BisaWhatsapp: boolean; Aktif: boolean };

export type PropsKotakTindakan = {
    Butir: ButirTindakan[];
    Izin: { Tandai: boolean };
    RingkasanWhatsapp?: RingkasanWhatsappTindakan;
};

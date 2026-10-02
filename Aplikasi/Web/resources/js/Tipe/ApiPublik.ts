/** X7 Open API v1: token API publik tenant (tanpa rahasia; hanya prefiks). */
export type TokenApi = {
    Uuid: string;
    Nama: string;
    Prefiks: string;
    Cakupan: string[];
    Aktif: boolean;
    DibuatPada: string | null;
    TerakhirDipakaiPada: string | null;
    KedaluwarsaPada: string | null;
    DicabutPada: string | null;
};

export type PropsHalamanTokenApi = {
    Token: TokenApi[];
    OpsiCakupan: { Nilai: string; Label: string }[];
    /** Token asli, hanya sekali setelah dibuat. */
    TokenBaru: { Nama: string; Token: string } | null;
    AlamatApi: string;
};

/** X7 bagian 2: webhook keluar (tanpa rahasia). */
export type WebhookTenant = {
    Uuid: string;
    Nama: string;
    Url: string;
    Peristiwa: string[];
    Aktif: boolean;
    DibuatPada: string | null;
};

export type StatusKirimanWebhook = 'Menunggu' | 'Terkirim' | 'Gagal';

/** Log kiriman (tanpa muatan). */
export type KirimanWebhook = {
    Uuid: string;
    NamaWebhook: string;
    Peristiwa: string;
    Status: StatusKirimanWebhook;
    Percobaan: number;
    KodeRespons: number | null;
    CuplikanRespons: string | null;
    BerikutnyaPada: string | null;
    TerkirimPada: string | null;
    DibuatPada: string | null;
    BisaKirimUlang: boolean;
};

export type PropsHalamanWebhook = {
    Webhook: WebhookTenant[];
    Kiriman: KirimanWebhook[];
    OpsiPeristiwa: { Nilai: string; Label: string }[];
    /** Rahasia penandatangan, hanya sekali setelah dibuat. */
    RahasiaBaru: { Nama: string; Rahasia: string } | null;
};

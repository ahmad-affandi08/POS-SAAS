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

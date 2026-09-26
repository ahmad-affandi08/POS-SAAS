import type { HasilTabel } from '@/Komponen/TabelData/Tipe';

/** F-07 mode service: reservasi layanan jasa. */
export type StatusReservasi = 'Menunggu' | 'Dikonfirmasi' | 'Hadir' | 'Selesai' | 'Batal' | 'TidakDatang';

export type BarisReservasi = {
    Uuid: string;
    Nomor: string;
    MulaiPada: string;
    SelesaiPada: string;
    NamaPelanggan: string;
    NoHp: string;
    Layanan: string;
    Staf: { Uuid: string; Nama: string } | null;
    Outlet: { Uuid: string | null; Nama: string };
    Status: StatusReservasi;
    LabelStatus: string;
    Sumber: 'BackOffice' | 'Online' | 'Pos';
    LabelSumber: string;
    Catatan: string | null;
    AlasanBatal: string | null;
    StatusBerikutnya: StatusReservasi[];
};

export type LayananReservasi = { Uuid: string; Nama: string; DurasiMenit: number; Harga: string | null };

export type SlotReservasi = { Jam: string; Staf: { Uuid: string; Nama: string }[] };

export type PengaturanReservasi = {
    OnlineAktif: boolean;
    KonfirmasiOtomatis: boolean;
    IntervalSlotMenit: number;
    JedaMenit: number;
    BatasHariKeDepan: number;
    MinimalMenitSebelum: number;
    PengingatAktif: boolean;
};

export type PropsDaftarReservasi = {
    Reservasi: HasilTabel<BarisReservasi>;
    OpsiStatus: { Nilai: StatusReservasi; Label: string }[];
    OpsiOutlet: { Uuid: string; Nama: string }[];
    OpsiStaf: { Uuid: string; Nama: string }[];
    OpsiLayanan: LayananReservasi[];
    Pengaturan: PengaturanReservasi;
    HariIni: string;
    TautanPublik: string;
    Izin: { Pengaturan: boolean };
};

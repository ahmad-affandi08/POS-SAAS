/** v1.98 Hardware Compatibility List (PRD §17.2.5a): satu model perangkat atau printer. */
export type StatusKompatibilitas = 'Tersertifikasi' | 'Kompatibel' | 'Terbatas' | 'BelumDiuji';

export type BarisKompatibilitas = {
    Uuid: string;
    Jenis: 'Perangkat' | 'Printer';
    Nama: string;
    Sambungan: string | null;
    Status: StatusKompatibilitas;
    LabelStatus: string;
    StatusOtomatis: StatusKompatibilitas;
    StatusManual: StatusKompatibilitas | null;
    Catatan: string | null;
    JumlahPerangkat: number;
    JumlahTenant: number;
    JumlahLolos: number;
    JumlahGagal: number;
    TerakhirDiujiPada: string | null;
    DisegarkanPada: string | null;
};

/** Warna label status HCL (status selalu disertai teks). */
export function AmbilJenisStatusKompatibilitas(status: StatusKompatibilitas): 'sukses' | 'peringatan' | 'netral' {
    return status === 'Tersertifikasi' || status === 'Kompatibel'
        ? 'sukses'
        : status === 'Terbatas'
          ? 'peringatan'
          : 'netral';
}

/** Label sambungan printer untuk HCL. */
export const LabelSambunganPrinter: Record<string, string> = {
    BluetoothKlasik: 'Bluetooth',
    Ble: 'Bluetooth LE',
    Usb: 'USB',
    SdkVendor: 'Printer bawaan',
};

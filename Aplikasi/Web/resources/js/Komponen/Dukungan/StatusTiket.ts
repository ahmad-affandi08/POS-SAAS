/** Status tiket dukungan (enum StatusTiketDukungan di Backend, P-09) dan jenis label tampilannya. */
export type StatusTiket = 'Baru' | 'Ditangani' | 'MenungguPelanggan' | 'Selesai' | 'Ditutup';

export const jenisLabelStatusTiket = {
    Baru: 'peringatan',
    Ditangani: 'netral',
    MenungguPelanggan: 'peringatan',
    Selesai: 'sukses',
    Ditutup: 'netral',
} as const;

export type BatasLampiran = { Maksimal: number; UkuranMaksimalKb: number; Ekstensi: string[] };

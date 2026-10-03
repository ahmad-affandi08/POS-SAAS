import { Head } from '@inertiajs/react';

import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';

export type PropsAbsensi = {
    NamaToko: string;
    NamaKaryawan: string;
    AlamatDasar: string;
    Wajah: { Status: 'Menunggu' | 'Disetujui' | 'Ditolak'; Label: string; AlasanTolak: string | null } | null;
    AbsensiTerbuka: { Uuid: string; MasukPada: string; NamaOutlet: string | null } | null;
    Riwayat: { MasukPada: string; KeluarPada: string | null; NamaOutlet: string | null }[];
    JumlahFotoDaftar: number;
};

/** F-18 bagian 4 (D-37): halaman absensi web karyawan. Kerangka; alur kamera & lokasi menyusul. */
export default function HalamanAbsensi({ NamaToko, NamaKaryawan }: PropsAbsensi) {
    return (
        <main className="mx-auto flex min-h-dvh max-w-md flex-col gap-4 px-4 py-6">
            <Head title={`Absen · ${NamaToko}`} />
            <JudulHalaman>{NamaKaryawan}</JudulHalaman>
        </main>
    );
}

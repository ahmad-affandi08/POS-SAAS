import type { ReactNode } from 'react';

type PropsPemberitahuan = {
    jenis: 'info' | 'sukses' | 'peringatan' | 'bahaya';
    judul?: string;
    children: ReactNode;
};

const kelasJenis = {
    info: 'border-info',
    sukses: 'border-sukses',
    peringatan: 'border-peringatan',
    bahaya: 'border-bahaya',
} as const;

const labelJenis = { info: 'Info', sukses: 'Berhasil', peringatan: 'Perhatian', bahaya: 'Galat' } as const;

/** Pesan status. Warna selalu disertai teks label agar tetap terbaca tanpa warna (PRD §17.6.11). */
export default function Pemberitahuan({ jenis, judul, children }: PropsPemberitahuan) {
    return (
        <div
            role={jenis === 'bahaya' || jenis === 'peringatan' ? 'alert' : 'status'}
            className={`rounded-panel border border-l-4 border-garis bg-permukaan px-4 py-3 ${kelasJenis[jenis]}`}
        >
            <p className="text-label font-semibold text-teks-utama">{judul ?? labelJenis[jenis]}</p>
            <div className="text-isi text-teks-sekunder">{children}</div>
        </div>
    );
}

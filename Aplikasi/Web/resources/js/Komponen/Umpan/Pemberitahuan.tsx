import { CircleCheckIcon, InfoIcon, OctagonXIcon, TriangleAlertIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/Komponen/Ui/alert';

type PropsPemberitahuan = {
    jenis: 'info' | 'sukses' | 'peringatan' | 'bahaya';
    judul?: string;
    children: ReactNode;
};

const kelasJenis = {
    info: 'border-l-info [&>svg]:text-info',
    sukses: 'border-l-sukses [&>svg]:text-sukses',
    peringatan: 'border-l-peringatan [&>svg]:text-peringatan',
    bahaya: 'border-l-bahaya [&>svg]:text-bahaya',
} as const;

const ikonJenis = { info: InfoIcon, sukses: CircleCheckIcon, peringatan: TriangleAlertIcon, bahaya: OctagonXIcon };

const labelJenis = { info: 'Info', sukses: 'Berhasil', peringatan: 'Perhatian', bahaya: 'Galat' } as const;

/** Pesan status. Warna selalu disertai teks label (dan ikon) agar tetap terbaca tanpa warna (PRD §17.6.11). */
export default function Pemberitahuan({ jenis, judul, children }: PropsPemberitahuan) {
    const Ikon = ikonJenis[jenis];

    return (
        <Alert
            role={jenis === 'bahaya' || jenis === 'peringatan' ? 'alert' : 'status'}
            data-jenis={jenis}
            className={`rounded-panel border-l-4 border-garis bg-permukaan px-4 py-3 ${kelasJenis[jenis]}`}
        >
            <Ikon aria-hidden="true" />
            <AlertTitle className="line-clamp-none min-w-0 text-label wrap-anywhere font-semibold tracking-normal text-teks-utama">
                {judul ?? labelJenis[jenis]}
            </AlertTitle>
            <AlertDescription className="block min-w-0 text-isi text-teks-sekunder wrap-anywhere">
                {children}
            </AlertDescription>
        </Alert>
    );
}

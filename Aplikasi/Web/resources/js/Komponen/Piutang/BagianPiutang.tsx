import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { IzinPiutang } from '@/Tipe/Piutang';

/** Bagian bersama halaman piutang pelanggan (F-12): alamat, label status, dan kerangka halaman daftar. */
export const AlamatPiutang = '/kelola/piutang';

export function AmbilJenisStatusPiutang(status: string): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    if (status === 'Lunas' || status === 'Diposting') {
        return 'sukses';
    }

    if (status === 'DibayarSebagian') {
        return 'peringatan';
    }

    return status === 'Dibatalkan' ? 'bahaya' : 'netral';
}

export function LabelStatusPiutang({ status, label }: { status: string; label: string }) {
    return <LabelStatus jenis={AmbilJenisStatusPiutang(status)} teks={label} />;
}

export function HalamanDaftarPiutang({
    judul,
    keterangan,
    izin,
    objek,
    children,
}: {
    judul: string;
    keterangan: string;
    izin: IzinPiutang;
    objek: string;
    children: ReactNode;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <TataLetakAplikasi judul={judul}>
            <p className="max-w-3xl text-isi text-teks-sekunder">{keterangan}</p>
            {!izin.Kelola ? <PesanHanyaLihat izin="akuntansi.kelola" objek={objek} /> : null}
            <DaftarGalatServer galat={props.errors} />
            {children}
        </TataLetakAplikasi>
    );
}

import { useId, type InputHTMLAttributes } from 'react';

import { Input } from '@/Komponen/Ui/input';

import {
    BuatKelasKontrol,
    GabungDijelaskanOleh,
    GalatBidang,
    KerangkaBidang,
    KeteranganBidang,
    LabelBidang,
} from './BagianBidang';

type PropsBidangTeks = {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    jenis?: 'text' | 'email' | 'password';
    kode?: boolean;
} & Pick<
    InputHTMLAttributes<HTMLInputElement>,
    'autoComplete' | 'autoFocus' | 'disabled' | 'inputMode' | 'maxLength' | 'required'
>;

/** Input teks dengan label, keterangan, dan pesan galat yang terhubung ke aria (PRD §17.6). */
export default function BidangTeks({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    jenis = 'text',
    kode = false,
    ...atribut
}: PropsBidangTeks) {
    const id = useId();
    const idKeterangan = `${id}-keterangan`;
    const idGalat = `${id}-galat`;

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <Input
                id={id}
                type={jenis}
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={GabungDijelaskanOleh(keterangan && idKeterangan, galat && idGalat)}
                className={BuatKelasKontrol(galat, kode ? 'font-mono tracking-wide' : undefined)}
                {...atribut}
            />
            {keterangan ? <KeteranganBidang id={idKeterangan}>{keterangan}</KeteranganBidang> : null}
            {galat ? <GalatBidang id={idGalat}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

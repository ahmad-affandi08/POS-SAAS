import { useId } from 'react';

import { Textarea } from '@/Komponen/Ui/textarea';

import {
    BuatKelasKontrol,
    GabungDijelaskanOleh,
    GalatBidang,
    KerangkaBidang,
    KeteranganBidang,
    LabelBidang,
} from './BagianBidang';

type PropsBidangTeksPanjang = {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    baris?: number;
    maksimal?: number;
    required?: boolean;
};

/** Area teks multi-baris dengan label, keterangan, dan galat terhubung ke aria (PRD §17.6). */
export default function BidangTeksPanjang({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    baris = 5,
    maksimal,
    required,
}: PropsBidangTeksPanjang) {
    const id = useId();

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <Textarea
                id={id}
                value={nilai}
                rows={baris}
                maxLength={maksimal}
                required={required}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={GabungDijelaskanOleh(keterangan && `${id}-keterangan`, galat && `${id}-galat`)}
                className={BuatKelasKontrol(galat, 'h-auto py-2 field-sizing-fixed')}
            />
            {keterangan ? <KeteranganBidang id={`${id}-keterangan`}>{keterangan}</KeteranganBidang> : null}
            {galat ? <GalatBidang id={`${id}-galat`}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

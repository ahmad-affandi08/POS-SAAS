import { useId } from 'react';

import { GalatBidang, KerangkaBidang, LabelBidang } from './BagianBidang';
import PilihanCari, { type OpsiPilihan } from './PilihanCari';

type PropsBidangPilihan = {
    label: string;
    nilai: string;
    opsi: OpsiPilihan[];
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    kosong?: string;
    disabled?: boolean;
    /** Wajib diisi: label otomatis diberi tanda * merah. */
    required?: boolean;
};

/** Pilihan tunggal dengan kotak cari (`PilihanCari`), label & galat terhubung (PRD §17.6). */
export default function BidangPilihan({
    label,
    nilai,
    opsi,
    saatBerubah,
    galat,
    kosong,
    disabled,
    required,
}: PropsBidangPilihan) {
    const id = useId();

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <PilihanCari
                id={id}
                label={label}
                nilai={nilai}
                opsi={opsi}
                saatBerubah={saatBerubah}
                kosong={kosong}
                galat={galat}
                disabled={disabled}
                required={required}
                aria-describedby={galat ? `${id}-galat` : undefined}
            />
            {galat ? <GalatBidang id={`${id}-galat`}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

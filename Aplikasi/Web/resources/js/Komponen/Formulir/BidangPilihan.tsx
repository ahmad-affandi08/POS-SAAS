import { useId } from 'react';

import { NativeSelect, NativeSelectOption } from '@/Komponen/Ui/native-select';

import { BuatKelasKontrol, GalatBidang, KerangkaBidang, LabelBidang } from './BagianBidang';

type PropsBidangPilihan = {
    label: string;
    nilai: string;
    opsi: { Nilai: string; Label: string }[];
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    kosong?: string;
};

/** Pilihan tunggal (select asli) dengan label & galat terhubung (PRD §17.6). */
export default function BidangPilihan({ label, nilai, opsi, saatBerubah, galat, kosong }: PropsBidangPilihan) {
    const id = useId();

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <div className="w-full [&>[data-slot=native-select-wrapper]]:w-full">
                <NativeSelect
                    id={id}
                    value={nilai}
                    onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                    aria-invalid={galat ? true : undefined}
                    aria-describedby={galat ? `${id}-galat` : undefined}
                    className={BuatKelasKontrol(galat)}
                >
                    {kosong !== undefined ? <NativeSelectOption value="">{kosong}</NativeSelectOption> : null}
                    {opsi.map((item) => (
                        <NativeSelectOption key={item.Nilai} value={item.Nilai}>
                            {item.Label}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
            </div>
            {galat ? <GalatBidang id={`${id}-galat`}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

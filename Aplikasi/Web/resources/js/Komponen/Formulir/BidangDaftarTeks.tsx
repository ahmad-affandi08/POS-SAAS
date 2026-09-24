import { useId } from 'react';

import { Textarea } from '@/Komponen/Ui/textarea';

import { BuatKelasKontrol, GalatBidang, KerangkaBidang, KeteranganBidang, LabelBidang } from './BagianBidang';

type PropsBidangDaftarTeks = {
    label: string;
    nilai: string[];
    saatBerubah: (nilai: string[]) => void;
    keterangan?: string;
    galat?: string | undefined;
    disabled?: boolean;
};

/** Daftar nama pendek, satu per baris (misal kategori, alasan void). Baris kosong diabaikan saat disimpan. */
export default function BidangDaftarTeks({
    label,
    nilai,
    saatBerubah,
    keterangan = 'Satu per baris.',
    galat,
    disabled,
}: PropsBidangDaftarTeks) {
    const id = useId();

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <Textarea
                id={id}
                rows={Math.max(3, nilai.length + 1)}
                value={nilai.join('\n')}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value.split('\n'))}
                disabled={disabled}
                aria-invalid={galat ? true : undefined}
                aria-describedby={`${id}-keterangan`}
                className={BuatKelasKontrol(galat, 'h-auto py-2 field-sizing-fixed')}
            />
            <KeteranganBidang id={`${id}-keterangan`}>{keterangan}</KeteranganBidang>
            {galat ? <GalatBidang>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

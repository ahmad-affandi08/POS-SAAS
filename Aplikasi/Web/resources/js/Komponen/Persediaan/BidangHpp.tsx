import { useId, useState, type ChangeEvent } from 'react';

import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from '@/Komponen/Ui/input-group';
import { Label } from '@/Komponen/Ui/label';
import { cn } from '@/Komponen/Ui/utils';
import { SkalaHpp } from '@/Pustaka/HitungDesimal';
import { CekMasukanJumlahValid, FormatMasukanJumlah, NormalisasiMasukanJumlah } from '@/Pustaka/MasukanJumlah';

/** Digit bulat HPP per satuan, sesuai aturan server `^\d{1,13}(\.\d{1,6})?$` (DesainF05a D). */
export const DigitBulatHpp = 13;

type PropsBidangHpp = {
    label: string;
    /** String desimal polos seperti yang dikirim ke server ("1234.5678"), atau "" bila kosong. */
    nilai: string;
    saatBerubah: (nilai: string) => void;
    /** Simbol satuan dasar, tampil sebagai "/ pcs" di ujung isian. */
    simbolSatuan?: string;
    galat?: string | undefined;
    keterangan?: string | undefined;
    disabled?: boolean;
    required?: boolean;
    labelTersembunyi?: boolean;
};

/**
 * Isian HPP (harga modal) per satuan dasar: sampai 6 desimal, tampil "Rp 1.234,5678" (koma desimal, angka tabular,
 * rata kanan), dikirim "1234.5678". Ketikan setengah jadi ("12,") dipertahankan selama fokus. Tanpa number/float.
 */
export default function BidangHpp({
    label,
    nilai,
    saatBerubah,
    simbolSatuan,
    galat,
    keterangan,
    disabled,
    required,
    labelTersembunyi = false,
}: PropsBidangHpp) {
    const id = useId();
    const [teksKetik, AturTeksKetik] = useState<string | null>(null);
    const opsi = { desimal: SkalaHpp, digitBulat: DigitBulatHpp };
    const tampil = teksKetik ?? FormatMasukanJumlah(nilai);
    const dijelaskanOleh = [keterangan ? `${id}-keterangan` : null, galat ? `${id}-galat` : null]
        .filter(Boolean)
        .join(' ');

    const Ubah = (peristiwa: ChangeEvent<HTMLInputElement>) => {
        const mentah = peristiwa.target.value;

        if (!CekMasukanJumlahValid(mentah, opsi)) {
            return;
        }

        AturTeksKetik(mentah);
        saatBerubah(NormalisasiMasukanJumlah(mentah, opsi));
    };

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className={labelTersembunyi ? 'sr-only' : 'text-label font-semibold text-teks-utama'}>
                {label}
            </Label>
            <InputGroup
                data-disabled={disabled ? true : undefined}
                className={cn('h-8 pointer-coarse:h-11', disabled && 'bg-latar')}
            >
                <InputGroupAddon align="inline-start" aria-hidden="true">
                    <InputGroupText className="text-isi font-normal text-teks-sekunder">Rp</InputGroupText>
                </InputGroupAddon>
                <InputGroupInput
                    id={id}
                    type="text"
                    inputMode="decimal"
                    autoComplete="off"
                    value={tampil}
                    onChange={Ubah}
                    onBlur={() => AturTeksKetik(null)}
                    disabled={disabled}
                    required={required}
                    aria-invalid={galat ? true : undefined}
                    aria-describedby={dijelaskanOleh || undefined}
                    className="text-right text-isi text-teks-utama tabular-nums disabled:text-teks-sekunder"
                />
                {simbolSatuan ? (
                    <InputGroupAddon align="inline-end" aria-hidden="true">
                        <InputGroupText className="text-isi font-normal text-teks-sekunder">
                            / {simbolSatuan}
                        </InputGroupText>
                    </InputGroupAddon>
                ) : null}
            </InputGroup>
            {keterangan ? (
                <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}

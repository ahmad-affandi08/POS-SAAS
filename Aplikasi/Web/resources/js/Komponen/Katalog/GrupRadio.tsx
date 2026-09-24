import { useId } from 'react';

import { Label } from '@/Komponen/Ui/label';
import { RadioGroup, RadioGroupItem } from '@/Komponen/Ui/radio-group';

type PropsGrupRadio<T extends string> = {
    legenda: string;
    nilai: T;
    opsi: { Nilai: T; Label: string; Keterangan?: string }[];
    saatBerubah: (nilai: T) => void;
    galat?: string | undefined;
    keterangan?: string;
    disabled?: boolean;
};

/** Pilihan tunggal (RadioGroup) dalam fieldset: target sentuh ≥ 40px, panah keyboard berpindah pilihan. */
export default function GrupRadio<T extends string>({
    legenda,
    nilai,
    opsi,
    saatBerubah,
    galat,
    keterangan,
    disabled,
}: PropsGrupRadio<T>) {
    const id = useId();

    return (
        <fieldset
            className="flex flex-col gap-1"
            aria-invalid={galat ? true : undefined}
            aria-describedby={[keterangan ? `${id}-keterangan` : null, galat ? `${id}-galat` : null]
                .filter(Boolean)
                .join(' ')}
        >
            <legend className="text-label font-semibold text-teks-utama">{legenda}</legend>
            {keterangan ? (
                <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            <RadioGroup
                value={nilai}
                onValueChange={(baru) => {
                    const pilihan = opsi.find((item) => item.Nilai === baru);

                    if (pilihan) {
                        saatBerubah(pilihan.Nilai);
                    }
                }}
                disabled={disabled}
                aria-label={legenda}
                className="gap-0.5"
            >
                {opsi.map((item) => (
                    <div key={item.Nilai} className="flex min-h-10 items-start gap-2 py-2">
                        <RadioGroupItem
                            id={`${id}-${item.Nilai}`}
                            value={item.Nilai}
                            aria-invalid={galat ? true : undefined}
                            className="mt-0.5"
                        />
                        <Label
                            htmlFor={`${id}-${item.Nilai}`}
                            className="block text-isi font-normal text-teks-utama"
                        >
                            {item.Label}
                            {item.Keterangan ? (
                                <span className="block text-keterangan text-teks-sekunder">{item.Keterangan}</span>
                            ) : null}
                        </Label>
                    </div>
                ))}
            </RadioGroup>
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </fieldset>
    );
}

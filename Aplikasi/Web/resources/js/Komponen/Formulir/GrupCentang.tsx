import { useId } from 'react';

import { Checkbox } from '@/Komponen/Ui/checkbox';
import { FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { Label } from '@/Komponen/Ui/label';

import { GalatBidang } from './BagianBidang';

type Opsi = { nilai: string; label: string };

type PropsGrupCentang = {
    legenda: string;
    opsi: Opsi[];
    terpilih: string[];
    saatBerubah: (terpilih: string[]) => void;
    galat?: string | undefined;
};

/** Sekumpulan kotak centang dengan legenda & galat (misal pilihan peran, boleh lebih dari satu). */
export default function GrupCentang({ legenda, opsi, terpilih, saatBerubah, galat }: PropsGrupCentang) {
    const id = useId();

    const Alihkan = (nilai: string) =>
        saatBerubah(terpilih.includes(nilai) ? terpilih.filter((item) => item !== nilai) : [...terpilih, nilai]);

    return (
        <FieldSet className="gap-2" aria-describedby={galat ? `${id}-galat` : undefined}>
            <FieldLegend variant="label" className="mb-0 text-label font-semibold text-teks-utama">
                {legenda}
            </FieldLegend>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {opsi.map((item, indeks) => (
                    <div key={item.nilai} className="flex min-h-10 items-center gap-2">
                        <Checkbox
                            id={`${id}-${String(indeks)}`}
                            checked={terpilih.includes(item.nilai)}
                            onCheckedChange={() => Alihkan(item.nilai)}
                            className="border-garis-input"
                        />
                        <Label
                            htmlFor={`${id}-${String(indeks)}`}
                            className="min-h-10 text-isi font-normal text-teks-utama"
                        >
                            {item.label}
                        </Label>
                    </div>
                ))}
            </div>
            {galat ? <GalatBidang id={`${id}-galat`}>{galat}</GalatBidang> : null}
        </FieldSet>
    );
}

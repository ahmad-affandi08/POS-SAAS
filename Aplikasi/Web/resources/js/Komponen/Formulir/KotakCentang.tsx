import { useId } from 'react';

import { Checkbox } from '@/Komponen/Ui/checkbox';
import { Label } from '@/Komponen/Ui/label';

type PropsKotakCentang = { label: string; nilai: boolean; saatBerubah: (nilai: boolean) => void };

/** Satu kotak centang dengan label yang bisa diklik (target sentuh ≥ 40px). */
export default function KotakCentang({ label, nilai, saatBerubah }: PropsKotakCentang) {
    const id = useId();

    return (
        <div className="flex min-h-10 items-center gap-2">
            <Checkbox
                id={id}
                checked={nilai}
                onCheckedChange={(status) => saatBerubah(status === true)}
                className="border-garis-input"
            />
            <Label htmlFor={id} className="min-h-10 text-isi font-normal text-teks-utama">
                {label}
            </Label>
        </div>
    );
}

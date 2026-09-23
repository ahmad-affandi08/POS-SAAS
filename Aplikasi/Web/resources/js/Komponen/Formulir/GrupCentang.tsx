import { useId } from 'react';

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
        <fieldset className="flex flex-col gap-2" aria-describedby={galat ? `${id}-galat` : undefined}>
            <legend className="text-label font-semibold text-teks-utama">{legenda}</legend>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {opsi.map((item) => (
                    <label key={item.nilai} className="flex min-h-10 items-center gap-2 text-isi text-teks-utama">
                        <input
                            type="checkbox"
                            className="size-4 accent-brand"
                            checked={terpilih.includes(item.nilai)}
                            onChange={() => Alihkan(item.nilai)}
                        />
                        {item.label}
                    </label>
                ))}
            </div>
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </fieldset>
    );
}

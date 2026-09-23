import { useId } from 'react';

type PropsGrupRadio<T extends string> = {
    legenda: string;
    nilai: T;
    opsi: { Nilai: T; Label: string; Keterangan?: string }[];
    saatBerubah: (nilai: T) => void;
    galat?: string | undefined;
    keterangan?: string;
    disabled?: boolean;
};

/** Pilihan tunggal sebagai radio asli dalam fieldset (target sentuh ≥ 40px, panah keyboard bawaan peramban). */
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
            <div className="flex flex-col gap-0.5">
                {opsi.map((item) => (
                    <label key={item.Nilai} className="flex min-h-10 items-start gap-2 py-2 text-isi text-teks-utama">
                        <input
                            type="radio"
                            name={id}
                            value={item.Nilai}
                            checked={nilai === item.Nilai}
                            disabled={disabled}
                            onChange={() => saatBerubah(item.Nilai)}
                            className="mt-0.5 size-4 accent-brand"
                        />
                        <span>
                            {item.Label}
                            {item.Keterangan ? (
                                <span className="block text-keterangan text-teks-sekunder">{item.Keterangan}</span>
                            ) : null}
                        </span>
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

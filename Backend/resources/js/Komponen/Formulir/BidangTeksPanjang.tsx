import { useId } from 'react';

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
    const dijelaskanOleh = [keterangan ? `${id}-keterangan` : null, galat ? `${id}-galat` : null]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <textarea
                id={id}
                value={nilai}
                rows={baris}
                maxLength={maksimal}
                required={required}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={dijelaskanOleh || undefined}
                className={`rounded-kontrol border bg-permukaan px-3 py-2 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                }`}
            />
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

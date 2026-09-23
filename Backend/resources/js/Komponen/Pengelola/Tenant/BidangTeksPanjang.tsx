import { useId } from 'react';

type PropsBidangTeksPanjang = {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    baris?: number;
    maksimal?: number;
};

/** Textarea dengan label, keterangan, dan galat terhubung ke aria (PRD §17.6). */
export default function BidangTeksPanjang({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    baris = 3,
    maksimal,
}: PropsBidangTeksPanjang) {
    const id = useId();
    const idKeterangan = `${id}-keterangan`;
    const idGalat = `${id}-galat`;
    const dijelaskanOleh = [keterangan ? idKeterangan : null, galat ? idGalat : null].filter(Boolean).join(' ');

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <textarea
                id={id}
                rows={baris}
                value={nilai}
                maxLength={maksimal}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={dijelaskanOleh || undefined}
                className={`rounded-kontrol border bg-permukaan px-3 py-2 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                }`}
            />
            {keterangan ? (
                <p id={idKeterangan} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            {galat ? (
                <p id={idGalat} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}

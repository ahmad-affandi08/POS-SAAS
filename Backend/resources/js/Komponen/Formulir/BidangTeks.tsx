import { useId, type InputHTMLAttributes } from 'react';

type PropsBidangTeks = {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    jenis?: 'text' | 'email' | 'password';
    kode?: boolean;
} & Pick<InputHTMLAttributes<HTMLInputElement>, 'autoComplete' | 'autoFocus' | 'inputMode' | 'maxLength' | 'required'>;

/** Input teks dengan label, keterangan, dan pesan galat yang terhubung ke aria (PRD §17.6). */
export default function BidangTeks({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    jenis = 'text',
    kode = false,
    ...atribut
}: PropsBidangTeks) {
    const id = useId();
    const idKeterangan = `${id}-keterangan`;
    const idGalat = `${id}-galat`;
    const dijelaskanOleh = [keterangan ? idKeterangan : null, galat ? idGalat : null].filter(Boolean).join(' ');

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <input
                id={id}
                type={jenis}
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={dijelaskanOleh || undefined}
                className={`h-10 rounded-kontrol border bg-permukaan px-3 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                } ${kode ? 'font-mono tracking-wide' : ''}`}
                {...atribut}
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

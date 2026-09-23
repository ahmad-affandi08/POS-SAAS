import { useId } from 'react';

type PropsBidangPilihan = {
    label: string;
    nilai: string;
    opsi: { Nilai: string; Label: string }[];
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    kosong?: string;
};

/** Pilihan tunggal (select asli) dengan label & galat terhubung (PRD §17.6). */
export default function BidangPilihan({ label, nilai, opsi, saatBerubah, galat, kosong }: PropsBidangPilihan) {
    const id = useId();

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <select
                id={id}
                value={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={galat ? `${id}-galat` : undefined}
                className={`h-10 rounded-kontrol border bg-permukaan px-3 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                }`}
            >
                {kosong !== undefined ? <option value="">{kosong}</option> : null}
                {opsi.map((item) => (
                    <option key={item.Nilai} value={item.Nilai}>
                        {item.Label}
                    </option>
                ))}
            </select>
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}

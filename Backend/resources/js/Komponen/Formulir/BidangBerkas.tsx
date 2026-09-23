import { useId, useRef } from 'react';

import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';

type PropsBidangBerkas = {
    label: string;
    berkas: File[];
    saatBerubah: (berkas: File[]) => void;
    ekstensi: string[];
    maksimal: number;
    ukuranMaksimalKb: number;
    galat?: string | undefined;
};

/** Pilih beberapa berkas lampiran dengan batas jumlah, jenis, dan ukuran yang disebutkan jelas (PRD §17.6.7). */
export default function BidangBerkas({
    label,
    berkas,
    saatBerubah,
    ekstensi,
    maksimal,
    ukuranMaksimalKb,
    galat,
}: PropsBidangBerkas) {
    const id = useId();
    const masukan = useRef<HTMLInputElement>(null);
    const keterangan = `Opsional. Maksimal ${maksimal} berkas, masing-masing ${FormatUkuranBerkas(ukuranMaksimalKb * 1024)} (${ekstensi.join(', ')}).`;
    const Hapus = (indeks: number) => {
        saatBerubah(berkas.filter((_, i) => i !== indeks));

        if (masukan.current) {
            masukan.current.value = '';
        }
    };

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <input
                ref={masukan}
                id={id}
                type="file"
                multiple
                accept={ekstensi.map((nilai) => `.${nilai}`).join(',')}
                onChange={(peristiwa) =>
                    saatBerubah([...berkas, ...Array.from(peristiwa.target.files ?? [])].slice(0, maksimal))
                }
                aria-invalid={galat ? true : undefined}
                aria-describedby={`${id}-keterangan${galat ? ` ${id}-galat` : ''}`}
                className="text-isi text-teks-utama file:mr-3 file:rounded-kontrol file:border file:border-garis-input file:bg-permukaan file:px-3 file:py-1 file:text-label file:font-semibold"
            />
            <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                {keterangan}
            </p>
            {berkas.length > 0 ? (
                <ul className="flex flex-col gap-1">
                    {berkas.map((file, indeks) => (
                        <li
                            key={`${file.name}-${String(indeks)}`}
                            className="flex items-center justify-between gap-2 text-keterangan"
                        >
                            <span className="break-all text-teks-utama">
                                {file.name} · {FormatUkuranBerkas(file.size)}
                            </span>
                            <button
                                type="button"
                                onClick={() => Hapus(indeks)}
                                className="font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                Hapus
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}

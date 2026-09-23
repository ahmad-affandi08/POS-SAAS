import { useId, useLayoutEffect, useRef, type ChangeEvent } from 'react';

import {
    CekMasukanUangValid,
    FormatMasukanUang,
    HitungDigitSebelumKursor,
    HitungPosisiKursor,
    NormalisasiMasukanUang,
} from '@/Pustaka/MasukanUang';

type PropsBidangUang = {
    label: string;
    /** String desimal polos seperti yang dikirim ke server ("15000"), atau "" bila kosong. */
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    disabled?: boolean;
    required?: boolean;
    /** Label hanya untuk pembaca layar, misal di dalam baris tabel yang judul kolomnya sudah "Harga". */
    labelTersembunyi?: boolean;
};

/**
 * Input Rupiah: tampil "Rp 15.000" (titik ribuan, angka tabular, rata kanan), dikirim "15000".
 * Karakter yang tidak valid diabaikan; tidak ada perhitungan number/float (CLAUDE.md #7).
 */
export default function BidangUang({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    disabled,
    required,
    labelTersembunyi = false,
}: PropsBidangUang) {
    const id = useId();
    const masukan = useRef<HTMLInputElement>(null);
    const digitKursor = useRef<number | null>(null);
    const tampil = FormatMasukanUang(nilai);
    const dijelaskanOleh = [keterangan ? `${id}-keterangan` : null, galat ? `${id}-galat` : null]
        .filter(Boolean)
        .join(' ');

    useLayoutEffect(() => {
        const elemen = masukan.current;

        if (digitKursor.current === null || elemen === null || document.activeElement !== elemen) {
            return;
        }

        const posisi = HitungPosisiKursor(elemen.value, digitKursor.current);
        elemen.setSelectionRange(posisi, posisi);
        digitKursor.current = null;
    }, [tampil]);

    const Ubah = (peristiwa: ChangeEvent<HTMLInputElement>) => {
        const mentah = peristiwa.target.value;

        if (!CekMasukanUangValid(mentah)) {
            return;
        }

        digitKursor.current = HitungDigitSebelumKursor(mentah, peristiwa.target.selectionStart ?? mentah.length);
        saatBerubah(NormalisasiMasukanUang(mentah));
    };

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className={labelTersembunyi ? 'sr-only' : 'text-label font-semibold text-teks-utama'}>
                {label}
            </label>
            <div
                className={`flex h-10 items-center rounded-kontrol border bg-permukaan focus-within:ring-2 focus-within:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                } ${disabled ? 'bg-latar' : ''}`}
            >
                <span aria-hidden="true" className="pl-3 text-isi text-teks-sekunder">
                    Rp
                </span>
                <input
                    ref={masukan}
                    id={id}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    value={tampil}
                    onChange={Ubah}
                    disabled={disabled}
                    required={required}
                    aria-invalid={galat ? true : undefined}
                    aria-describedby={dijelaskanOleh || undefined}
                    className="h-full w-full min-w-0 rounded-kontrol bg-transparent px-3 text-right text-isi text-teks-utama tabular-nums outline-none disabled:text-teks-sekunder"
                />
            </div>
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

import { useEffect, useId, useMemo, useRef, useState, type ChangeEvent } from 'react';

import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';

type PropsBidangGambar = {
    label: string;
    /** Berkas baru yang dipilih (belum diunggah). */
    berkas: File | null;
    saatBerubah: (berkas: File | null) => void;
    /** Gambar yang sudah tersimpan di server, bila ada. */
    tautanSaatIni?: string | null;
    /** Bila diisi, gambar tersimpan bisa dihapus (misal logo). */
    saatHapusSaatIni?: () => void;
    labelHapus?: string;
    ukuranMaksimalKb: number;
    ekstensi: string[];
    keterangan?: string;
    galat?: string | undefined;
    disabled?: boolean;
};

/** Nama ekstensi berkas dalam huruf kecil, tanpa titik ("Logo.PNG" → "png"). */
export function AmbilEkstensiBerkas(nama: string): string {
    const titik = nama.lastIndexOf('.');

    return titik < 0 ? '' : nama.slice(titik + 1).toLowerCase();
}

/** Pesan galat lokal sebelum unggah; server tetap memvalidasi ulang. Null = berkas diterima. */
export function PeriksaBerkasGambar(berkas: File, ekstensi: string[], ukuranMaksimalKb: number): string | null {
    if (!ekstensi.includes(AmbilEkstensiBerkas(berkas.name))) {
        return `Format ${berkas.name} tidak didukung. Pilih gambar ${ekstensi.join(', ')}.`;
    }

    if (berkas.size > ukuranMaksimalKb * 1024) {
        return `Ukuran ${FormatUkuranBerkas(berkas.size)} melebihi batas ${FormatUkuranBerkas(ukuranMaksimalKb * 1024)}. Perkecil gambar lalu pilih lagi.`;
    }

    return null;
}

/** Satu gambar (logo, QRIS) dengan pratinjau, petunjuk ukuran & format, dan pemeriksaan awal di peramban. */
export default function BidangGambar({
    label,
    berkas,
    saatBerubah,
    tautanSaatIni = null,
    saatHapusSaatIni,
    labelHapus = 'Hapus gambar',
    ukuranMaksimalKb,
    ekstensi,
    keterangan,
    galat,
    disabled,
}: PropsBidangGambar) {
    const id = useId();
    const masukan = useRef<HTMLInputElement>(null);
    const [galatLokal, AturGalatLokal] = useState<string | null>(null);
    const pratinjau = useMemo(
        () => (berkas !== null && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(berkas) : null),
        [berkas],
    );
    const petunjuk = `${keterangan ? `${keterangan} ` : ''}Format ${ekstensi.join(', ')}, maksimal ${FormatUkuranBerkas(ukuranMaksimalKb * 1024)}.`;
    const pesanGalat = galatLokal ?? galat;
    const sumberGambar = berkas ? pratinjau : tautanSaatIni;

    useEffect(
        () => () => {
            if (pratinjau !== null) {
                URL.revokeObjectURL(pratinjau);
            }
        },
        [pratinjau],
    );

    const Pilih = (peristiwa: ChangeEvent<HTMLInputElement>) => {
        const terpilih = peristiwa.target.files?.[0] ?? null;

        if (terpilih === null) {
            return;
        }

        const pesan = PeriksaBerkasGambar(terpilih, ekstensi, ukuranMaksimalKb);
        AturGalatLokal(pesan);

        if (pesan !== null) {
            peristiwa.target.value = '';

            return;
        }

        saatBerubah(terpilih);
    };

    const Batalkan = () => {
        saatBerubah(null);
        AturGalatLokal(null);

        if (masukan.current) {
            masukan.current.value = '';
        }
    };

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div className="flex size-32 shrink-0 items-center justify-center overflow-hidden rounded-kontrol border border-garis bg-latar">
                    {sumberGambar ? (
                        <img
                            src={sumberGambar}
                            alt={berkas ? `Pratinjau ${berkas.name}` : `${label} saat ini`}
                            className="max-h-full max-w-full object-contain"
                        />
                    ) : (
                        <span className="px-2 text-center text-keterangan text-teks-sekunder">
                            {berkas ? berkas.name : 'Belum ada gambar'}
                        </span>
                    )}
                </div>
                <div className="flex min-w-0 flex-col gap-2">
                    <input
                        ref={masukan}
                        id={id}
                        type="file"
                        accept={ekstensi.map((nilai) => `.${nilai}`).join(',')}
                        onChange={Pilih}
                        disabled={disabled}
                        aria-invalid={pesanGalat ? true : undefined}
                        aria-describedby={`${id}-keterangan${pesanGalat ? ` ${id}-galat` : ''}`}
                        className="text-isi text-teks-utama file:mr-3 file:rounded-kontrol file:border file:border-garis-input file:bg-permukaan file:px-3 file:py-1 file:text-label file:font-semibold"
                    />
                    <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                        {petunjuk}
                    </p>
                    {berkas ? (
                        <p className="flex flex-wrap items-center gap-2 text-keterangan text-teks-utama">
                            <span className="break-all">
                                {berkas.name} · {FormatUkuranBerkas(berkas.size)}
                            </span>
                            <button
                                type="button"
                                onClick={Batalkan}
                                className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                Batal pilih
                            </button>
                        </p>
                    ) : tautanSaatIni && saatHapusSaatIni ? (
                        <p>
                            <button
                                type="button"
                                onClick={saatHapusSaatIni}
                                disabled={disabled}
                                className="text-keterangan font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                {labelHapus}
                            </button>
                        </p>
                    ) : null}
                </div>
            </div>
            <div aria-live="polite">
                {pesanGalat ? (
                    <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                        {pesanGalat}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

import { useId } from 'react';

type PropsBidangDaftarTeks = {
    label: string;
    nilai: string[];
    saatBerubah: (nilai: string[]) => void;
    keterangan?: string;
    galat?: string | undefined;
    disabled?: boolean;
};

/** Daftar nama pendek, satu per baris (misal kategori, alasan void). Baris kosong diabaikan saat disimpan. */
export default function BidangDaftarTeks({
    label,
    nilai,
    saatBerubah,
    keterangan = 'Satu per baris.',
    galat,
    disabled,
}: PropsBidangDaftarTeks) {
    const id = useId();

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <textarea
                id={id}
                rows={Math.max(3, nilai.length + 1)}
                value={nilai.join('\n')}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value.split('\n'))}
                disabled={disabled}
                aria-invalid={galat ? true : undefined}
                aria-describedby={`${id}-keterangan`}
                className={`rounded-kontrol border bg-permukaan px-3 py-2 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:bg-latar disabled:text-teks-sekunder ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                }`}
            />
            <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                {keterangan}
            </p>
            {galat ? <p className="text-keterangan font-semibold text-bahaya">{galat}</p> : null}
        </div>
    );
}

type PropsLabelStatus = {
    jenis: 'sukses' | 'peringatan' | 'bahaya' | 'netral';
    teks: string;
};

const kelasJenis = {
    sukses: 'border-sukses text-sukses',
    peringatan: 'border-peringatan text-peringatan',
    bahaya: 'border-bahaya text-bahaya',
    netral: 'border-garis-input text-teks-sekunder',
} as const;

/** Label status pendek. Selalu berisi teks, warna hanya penguat (PRD §17.6.3). */
export default function LabelStatus({ jenis, teks }: PropsLabelStatus) {
    return (
        <span
            className={`inline-flex rounded-kontrol border px-2 py-0.5 text-keterangan font-semibold ${kelasJenis[jenis]}`}
        >
            {teks}
        </span>
    );
}

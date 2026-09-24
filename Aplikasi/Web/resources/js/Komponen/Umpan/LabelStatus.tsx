import { Badge } from '@/Komponen/Ui/badge';

type PropsLabelStatus = {
    jenis: 'sukses' | 'peringatan' | 'bahaya' | 'netral';
    teks: string;
};

const kelasJenis = {
    sukses: 'border-sukses bg-sukses-lembut text-sukses',
    peringatan: 'border-peringatan bg-peringatan-lembut text-peringatan',
    bahaya: 'border-bahaya bg-bahaya-lembut text-bahaya',
    netral: 'border-garis-input bg-permukaan-redup text-teks-sekunder',
} as const;

/** Label status pendek. Selalu berisi teks, warna hanya penguat (PRD §17.6.3). */
export default function LabelStatus({ jenis, teks }: PropsLabelStatus) {
    return (
        <Badge
            variant="outline"
            data-jenis={jenis}
            className={`rounded-kontrol text-keterangan font-semibold ${kelasJenis[jenis]}`}
        >
            {teks}
        </Badge>
    );
}

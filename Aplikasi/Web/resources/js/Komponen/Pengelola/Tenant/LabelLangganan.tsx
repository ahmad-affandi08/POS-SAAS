import LabelStatus from '@/Komponen/Umpan/LabelStatus';

const jenisStatus = {
    Trial: 'netral',
    Aktif: 'sukses',
    Tertunggak: 'peringatan',
    Ditangguhkan: 'bahaya',
    Berhenti: 'netral',
    Gratis: 'netral',
} as const;

/** Status langganan tenant (F-00 BR-00.7). Teks selalu tampil; warna hanya penguat. */
export function LabelStatusLangganan({ status }: { status: string | null }) {
    if (status === null) {
        return <LabelStatus jenis="bahaya" teks="Tanpa langganan" />;
    }

    const jenis = status in jenisStatus ? jenisStatus[status as keyof typeof jenisStatus] : 'netral';

    return <LabelStatus jenis={jenis} teks={status} />;
}

/** Penanda tenant Uji/Demo/Internal (P-07): dikecualikan dari metrik bisnis. */
export function LabelPenanda({ penanda }: { penanda: string | null }) {
    return penanda === null ? null : <LabelStatus jenis="peringatan" teks={`Penanda: ${penanda}`} />;
}

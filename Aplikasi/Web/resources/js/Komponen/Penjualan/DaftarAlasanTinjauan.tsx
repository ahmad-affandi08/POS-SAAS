import type { AlasanTinjauan } from '@/Tipe/Penjualan';

type PropsDaftarAlasanTinjauan = {
    alasan: AlasanTinjauan[];
    /** Kalimat bila server tidak menyimpan alasan. */
    cadangan: string;
};

/** Alasan tinjauan dokumen POS dalam kalimat manusiawi: label tebal lalu keterangannya (PRD v1.46). */
export default function DaftarAlasanTinjauan({ alasan, cadangan }: PropsDaftarAlasanTinjauan) {
    if (alasan.length === 0) {
        return <>{cadangan}</>;
    }

    return (
        <ul className="grid gap-1">
            {alasan.map((a, i) => (
                <li key={`${a.Kode ?? 'Lain'}-${i}`} className="break-words">
                    <span className="font-semibold">{a.Label}</span>
                    {a.Keterangan !== '' ? <>: {a.Keterangan}</> : null}
                </li>
            ))}
        </ul>
    );
}

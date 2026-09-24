import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { AmbilJenisLabelStatusStokAwal } from '@/Pustaka/FormatPersediaan';
import type { StatusStokAwal } from '@/Tipe/Persediaan';

/** Label status dokumen stok awal: teks dari server (`LabelStatus`), warna hanya penguat (§17.6.3). */
export default function LabelStatusStokAwal({ status, label }: { status: StatusStokAwal; label: string }) {
    return <LabelStatus jenis={AmbilJenisLabelStatusStokAwal(status)} teks={label} />;
}

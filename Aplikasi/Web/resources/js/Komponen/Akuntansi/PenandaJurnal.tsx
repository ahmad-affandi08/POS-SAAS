import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import type { BarisDaftarJurnal } from '@/Tipe/Akuntansi';

type PropsPenandaJurnal = { jurnal: Pick<BarisDaftarJurnal, 'Otomatis' | 'Dibalik' | 'Pembalik'> };

/**
 * Penanda sifat jurnal (DesainF05a E): pembalik, sudah dibalik, dan manual. Selalu berteks; warna hanya penguat
 * (PRD §17.6.3). Jurnal otomatis biasa tanpa penanda.
 */
export default function PenandaJurnal({ jurnal }: PropsPenandaJurnal) {
    const penanda: { kunci: string; jenis: 'peringatan' | 'netral'; teks: string }[] = [];

    if (jurnal.Pembalik) {
        penanda.push({ kunci: 'pembalik', jenis: 'peringatan', teks: 'Jurnal pembalik' });
    }

    if (jurnal.Dibalik) {
        penanda.push({ kunci: 'dibalik', jenis: 'netral', teks: 'Sudah dibalik' });
    }

    if (!jurnal.Otomatis) {
        penanda.push({ kunci: 'manual', jenis: 'netral', teks: 'Manual' });
    }

    if (penanda.length === 0) {
        return null;
    }

    return (
        <span className="inline-flex flex-wrap gap-1">
            {penanda.map((satu) => (
                <LabelStatus key={satu.kunci} jenis={satu.jenis} teks={satu.teks} />
            ))}
        </span>
    );
}

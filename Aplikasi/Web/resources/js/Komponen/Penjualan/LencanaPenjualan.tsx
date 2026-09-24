import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import type { StatusPenjualan } from '@/Tipe/Penjualan';

const jenis: Record<StatusPenjualan, 'sukses' | 'peringatan' | 'bahaya' | 'netral'> = {
    Lunas: 'sukses',
    Void: 'bahaya',
    Diretur: 'netral',
};

/** Status penjualan (teks dari server) dan penanda "Perlu ditinjau" (stok tidak cukup, pilihan/produk dihapus). */
export default function LencanaPenjualan({
    status,
    label,
    perluTinjauan,
}: {
    status: StatusPenjualan;
    label: string;
    perluTinjauan: boolean;
}) {
    return (
        <span className="flex flex-wrap gap-1">
            <LabelStatus jenis={jenis[status]} teks={label} />
            {perluTinjauan ? <LabelStatus jenis="peringatan" teks="Perlu ditinjau" /> : null}
        </span>
    );
}

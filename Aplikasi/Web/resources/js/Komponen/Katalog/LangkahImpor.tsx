import type { StatusImporProduk } from '@/Tipe/Katalog';

/** Jenis label status impor; teks label dari server. */
export function JenisLabelImpor(status: StatusImporProduk): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    switch (status) {
        case 'Selesai':
            return 'sukses';
        case 'Gagal':
            return 'bahaya';
        case 'Pratinjau':
        case 'MenungguPemetaan':
            return 'peringatan';
        default:
            return 'netral';
    }
}

const daftarLangkah = ['Unggah berkas', 'Pemetaan kolom', 'Pratinjau', 'Proses impor', 'Laporan'] as const;

/** Nomor langkah wizard impor (0-based) untuk status impor (DesainF03 C.6 mesin status). */
export function AmbilLangkahImpor(status: StatusImporProduk | null): number {
    switch (status) {
        case null:
        case 'Diunggah':
            return 0;
        case 'MenungguPemetaan':
            return 1;
        case 'Memvalidasi':
        case 'Pratinjau':
            return 2;
        case 'Menerapkan':
        case 'Gagal':
            return 3;
        case 'Selesai':
        case 'Dibatalkan':
            return 4;
    }
}

/** Penanda langkah impor: unggah → pemetaan kolom → pratinjau → proses → laporan. Status tertulis, bukan warna saja. */
export default function LangkahImpor({ status }: { status: StatusImporProduk | null }) {
    const aktif = AmbilLangkahImpor(status);

    return (
        <ol aria-label="Langkah impor produk" className="flex flex-wrap gap-x-4 gap-y-1 text-label">
            {daftarLangkah.map((label, indeks) => (
                <li
                    key={label}
                    aria-current={indeks === aktif ? 'step' : undefined}
                    className={indeks === aktif ? 'font-semibold text-teks-utama' : 'text-teks-sekunder'}
                >
                    <span className="tabular-nums">{indeks + 1}.</span> {label}
                    {indeks < aktif ? <span className="sr-only"> (selesai)</span> : null}
                    {indeks === aktif ? <span className="sr-only"> (langkah saat ini)</span> : null}
                </li>
            ))}
        </ol>
    );
}

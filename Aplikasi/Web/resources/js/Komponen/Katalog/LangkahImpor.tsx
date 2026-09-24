import { Progress } from '@/Komponen/Ui/progress';
import { cn } from '@/Komponen/Ui/utils';
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

/**
 * Penanda langkah impor (stepper): unggah → pemetaan kolom → pratinjau → proses → laporan.
 * Status tertulis (nomor, aria-current, teks tersembunyi), bukan warna saja; batang Progress hanya visual.
 */
export default function LangkahImpor({ status }: { status: StatusImporProduk | null }) {
    const aktif = AmbilLangkahImpor(status);
    const persen = Math.round((aktif * 100) / (daftarLangkah.length - 1));

    return (
        <div className="flex flex-col gap-2">
            <ol aria-label="Langkah impor produk" className="flex flex-wrap gap-x-4 gap-y-2 text-label">
                {daftarLangkah.map((label, indeks) => (
                    <li
                        key={label}
                        aria-current={indeks === aktif ? 'step' : undefined}
                        className={cn(
                            'flex items-center gap-2',
                            indeks === aktif ? 'font-semibold text-teks-utama' : 'text-teks-sekunder',
                        )}
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full border text-keterangan tabular-nums',
                                indeks < aktif && 'border-primary bg-primary text-primary-foreground',
                                indeks === aktif && 'border-primary text-primary',
                                indeks > aktif && 'border-garis-input',
                            )}
                        >
                            {indeks + 1}
                        </span>
                        <span>
                            <span className="sr-only">{indeks + 1}. </span>
                            {label}
                        </span>
                        {indeks < aktif ? <span className="sr-only"> (selesai)</span> : null}
                        {indeks === aktif ? <span className="sr-only"> (langkah saat ini)</span> : null}
                    </li>
                ))}
            </ol>
            <Progress value={persen} aria-hidden="true" className="h-1" />
        </div>
    );
}

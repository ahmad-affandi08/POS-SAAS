import { Skeleton } from '@/Komponen/Ui/skeleton';

/** Keadaan memuat tabel persediaan (§17.6.6): kerangka baris, diumumkan lewat teks `label` untuk pembaca layar. */
export default function KerangkaMemuat({ label, baris = 5 }: { label: string; baris?: number }) {
    return (
        <div role="status" className="flex flex-col gap-2 rounded-panel border border-garis bg-card p-4">
            <span className="sr-only">{label}</span>
            {Array.from({ length: baris }, (_, indeks) => (
                <Skeleton key={indeks} aria-hidden="true" className="h-8 rounded-kontrol" />
            ))}
        </div>
    );
}

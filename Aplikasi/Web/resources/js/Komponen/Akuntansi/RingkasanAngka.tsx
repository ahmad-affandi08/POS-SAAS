import { cn } from '@/Komponen/Ui/utils';
import { FormatRupiah } from '@/Pustaka/Format';

export type ItemRingkasan = { label: string; nilai: string; keterangan?: string | undefined; tebal?: boolean };

/** Deretan angka ringkasan laporan (uang tabular rata kanan), satu kolom di HP dan berjajar di layar lebar. */
export default function RingkasanAngka({ label, item }: { label: string; item: ItemRingkasan[] }) {
    return (
        <dl aria-label={label} className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {item.map((satu) => (
                <div
                    key={satu.label}
                    className="flex flex-col gap-0.5 rounded-panel border border-garis bg-permukaan px-3 py-2"
                >
                    <dt className="text-label font-semibold text-teks-sekunder">{satu.label}</dt>
                    <dd
                        className={cn(
                            'text-right text-isi tabular-nums text-teks-utama',
                            satu.tebal && 'font-semibold',
                        )}
                    >
                        {FormatRupiah(satu.nilai)}
                    </dd>
                    {satu.keterangan ? <dd className="text-label text-teks-sekunder">{satu.keterangan}</dd> : null}
                </div>
            ))}
        </dl>
    );
}

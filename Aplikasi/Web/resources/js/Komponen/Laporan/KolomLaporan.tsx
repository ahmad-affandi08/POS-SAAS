import type { KolomTabel, PrioritasKolom } from '@/Komponen/TabelData/Tipe';
import { FormatRupiah } from '@/Pustaka/Format';
import { BandingkanDesimal } from '@/Pustaka/HitungDesimal';
import { FormatJumlahStok } from '@/Pustaka/FormatPersediaan';

/** Kolom uang laporan: string desimal → "Rp 1.250.000", rata kanan & tabular (meta `angka`). */
export function KolomUang<T extends object>(
    id: keyof T & string,
    label: string,
    prioritas: PrioritasKolom = 'penting',
): KolomTabel<T> {
    return {
        id,
        accessorKey: id,
        header: label,
        meta: { label, angka: true, prioritas, kelasSel: 'whitespace-nowrap' },
        sortingFn: (a, b, kolom) => BandingkanDesimal(String(a.getValue(kolom)), String(b.getValue(kolom))),
        cell: ({ row }) => FormatRupiah(String(row.original[id])),
    } as KolomTabel<T>;
}

/** Kolom bilangan bulat (jumlah transaksi dsb.). */
export function KolomBilangan<T extends object>(
    id: keyof T & string,
    label: string,
    prioritas: PrioritasKolom = 'rendah',
): KolomTabel<T> {
    return {
        id,
        accessorKey: id,
        header: label,
        meta: { label, angka: true, prioritas },
        cell: ({ row }) => String(row.original[id]),
    } as KolomTabel<T>;
}

/** Kolom kuantitas (string desimal 4 digit) tanpa nol berlebih. */
export function KolomQty<T extends object>(id: keyof T & string, label: string): KolomTabel<T> {
    return {
        id,
        accessorKey: id,
        header: label,
        meta: { label, angka: true, prioritas: 'penting' },
        sortingFn: (a, b, kolom) => BandingkanDesimal(String(a.getValue(kolom)), String(b.getValue(kolom))),
        cell: ({ row }) => FormatJumlahStok(String(row.original[id])),
    } as KolomTabel<T>;
}

/** Kolom angka baku laporan penjualan: kotor, diskon, retur, bersih, HPP, laba kotor, transaksi. */
export function KolomAngkaPenjualan<
    T extends { Kotor: string; Diskon: string; Retur: string; Bersih: string; Hpp: string; LabaKotor: string },
>(): KolomTabel<T>[] {
    return [
        KolomUang<T>('Kotor', 'Kotor', 'rendah'),
        KolomUang<T>('Diskon', 'Diskon', 'rendah'),
        KolomUang<T>('Retur', 'Retur', 'rendah'),
        KolomUang<T>('Bersih', 'Bersih', 'penting'),
        KolomUang<T>('Hpp', 'HPP', 'rendah'),
        KolomUang<T>('LabaKotor', 'Laba kotor', 'penting'),
    ];
}

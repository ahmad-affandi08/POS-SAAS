import { Link } from '@inertiajs/react';

import { cn } from '@/Komponen/Ui/utils';

/**
 * Satu gaya tab untuk seluruh web (garis bawah brand). Dipakai oleh tab tautan (`TabTautan`) dan tab panel Radix
 * (`kelasDaftarTabPanel`/`kelasItemTabPanel`) supaya semua tab tampil sama. Satu baris; di layar sempit bisa digulir
 * menyamping di dalam bilah tab (bukan gulir halaman).
 */
export const kelasDaftarTab = 'flex w-full justify-start gap-1 overflow-x-auto border-b border-garis';

const kelasItemTab =
    '-mb-px inline-flex shrink-0 items-center gap-1 border-b-2 px-3 py-2 text-label font-semibold whitespace-nowrap outline-none transition-colors focus-visible:ring-2 focus-visible:ring-brand/40 pointer-coarse:min-h-11';

export function KelasItemTab(aktif: boolean): string {
    return cn(
        kelasItemTab,
        aktif ? 'border-brand text-teks-utama' : 'border-transparent text-teks-sekunder hover:text-teks-utama',
    );
}

/** Untuk `TabsList` shadcn (tab panel di satu halaman). */
export const kelasDaftarTabPanel = cn(
    kelasDaftarTab,
    'h-auto rounded-none bg-transparent p-0 group-data-[orientation=horizontal]/tabs:h-auto',
);

/** Untuk `TabsTrigger` shadcn: keadaan aktif dari `data-state`. */
export const kelasItemTabPanel = cn(
    kelasItemTab,
    'h-auto flex-none rounded-none border-0 border-b-2 border-transparent bg-transparent text-teks-sekunder shadow-none after:hidden hover:text-teks-utama data-[state=active]:border-brand data-[state=active]:bg-transparent data-[state=active]:text-teks-utama data-[state=active]:shadow-none',
);

export type ItemTabTautan = { label: string; href: string; aktif: boolean };

type PropsTabTautan = {
    label: string;
    tab: ItemTabTautan[];
    /** Pertahankan posisi gulir saat pindah tab (misal tab laporan dengan saring yang sama). */
    pertahankanGulir?: boolean;
    className?: string;
};

/** Tab navigasi antar halaman (tautan Inertia); tab aktif ditandai `aria-current="page"` dan garis bawah brand. */
export default function TabTautan({ label, tab, pertahankanGulir = false, className }: PropsTabTautan) {
    return (
        <nav aria-label={label} className={cn(kelasDaftarTab, className)}>
            {tab.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    preserveScroll={pertahankanGulir}
                    aria-current={item.aktif ? 'page' : undefined}
                    className={KelasItemTab(item.aktif)}
                >
                    {item.label}
                </Link>
            ))}
        </nav>
    );
}

import { Link, usePage } from '@inertiajs/react';

import { Button } from '@/Komponen/Ui/button';
import { cn } from '@/Komponen/Ui/utils';

type PropsNavigasiTab = {
    label: string;
    daftarTab: { label: string; href: string }[];
};

/**
 * Tab navigasi antar halaman (tautan Inertia, bukan panel `Tabs`): tab aktif ditandai `aria-current="page"`
 * dan garis bawah brand, sehingga tetap terbaca tanpa warna.
 */
export default function NavigasiTab({ label, daftarTab }: PropsNavigasiTab) {
    const { url } = usePage();

    return (
        <nav aria-label={label} className="flex flex-wrap gap-1 border-b border-garis">
            {daftarTab.map((tab) => {
                const aktif = url.startsWith(tab.href);

                return (
                    <Button
                        key={tab.href}
                        asChild
                        variant="ghost"
                        className={cn(
                            '-mb-px h-auto rounded-b-none border-b-2 px-3 py-2 text-label font-semibold',
                            aktif ? 'border-brand text-teks-utama' : 'border-transparent text-teks-sekunder',
                        )}
                    >
                        <Link href={tab.href} aria-current={aktif ? 'page' : undefined}>
                            {tab.label}
                        </Link>
                    </Button>
                );
            })}
        </nav>
    );
}

import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/Komponen/Ui/utils';

const daftarTab = [
    { label: 'Pengguna', href: '/kelola/pengguna' },
    { label: 'Peran & izin', href: '/kelola/peran' },
];

/**
 * Navigasi antara daftar pengguna dan peran (F-02 langkah 3, §19.1). Tampil seperti `TabsList` shadcn/ui, tetapi
 * tetap berupa tautan halaman (bukan panel Tabs) karena tiap tab adalah URL Inertia sendiri.
 */
export default function TabPengguna() {
    const { url } = usePage();

    return (
        <nav
            aria-label="Pengguna & peran"
            className="inline-flex w-fit flex-wrap items-center gap-1 rounded-panel bg-muted p-1 text-muted-foreground"
        >
            {daftarTab.map((tab) => {
                const aktif = url.startsWith(tab.href);

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        aria-current={aktif ? 'page' : undefined}
                        data-state={aktif ? 'active' : 'inactive'}
                        className={cn(
                            'inline-flex h-8 items-center rounded-kontrol px-3 text-label font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            aktif
                                ? 'bg-background text-teks-utama shadow-sm'
                                : 'text-teks-sekunder hover:text-teks-utama',
                        )}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}

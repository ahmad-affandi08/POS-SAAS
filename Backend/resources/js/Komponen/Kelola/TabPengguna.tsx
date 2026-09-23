import { Link, usePage } from '@inertiajs/react';

const daftarTab = [
    { label: 'Pengguna', href: '/kelola/pengguna' },
    { label: 'Peran & izin', href: '/kelola/peran' },
];

/** Navigasi antara daftar pengguna dan peran (F-02 langkah 3, §19.1). */
export default function TabPengguna() {
    const { url } = usePage();

    return (
        <nav aria-label="Pengguna & peran" className="flex flex-wrap gap-1 border-b border-garis">
            {daftarTab.map((tab) => {
                const aktif = url.startsWith(tab.href);

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        aria-current={aktif ? 'page' : undefined}
                        className={`-mb-px border-b-2 px-3 py-2 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                            aktif ? 'border-brand text-teks-utama' : 'border-transparent text-teks-sekunder'
                        }`}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}

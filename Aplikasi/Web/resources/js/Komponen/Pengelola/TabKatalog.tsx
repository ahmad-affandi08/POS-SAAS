import { Link, usePage } from '@inertiajs/react';

const daftarTab = [
    { label: 'Paket', href: '/katalog/paket' },
    { label: 'Add-on', href: '/katalog/add-on' },
    { label: 'Kupon', href: '/katalog/kupon' },
    { label: 'Fitur', href: '/katalog/fitur' },
];

/** Navigasi antar data katalog P-04. */
export default function TabKatalog() {
    const { url } = usePage();

    return (
        <nav aria-label="Katalog" className="flex flex-wrap gap-1 border-b border-garis">
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

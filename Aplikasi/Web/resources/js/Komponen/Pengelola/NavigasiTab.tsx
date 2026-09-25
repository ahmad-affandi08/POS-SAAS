import { usePage } from '@inertiajs/react';

import TabTautan from '@/Komponen/Navigasi/TabTautan';

type PropsNavigasiTab = {
    label: string;
    daftarTab: { label: string; href: string }[];
};

/** Tab navigasi antar halaman Platform Pengelola (gaya tab bersama `TabTautan`). */
export default function NavigasiTab({ label, daftarTab }: PropsNavigasiTab) {
    const { url } = usePage();

    return <TabTautan label={label} tab={daftarTab.map((tab) => ({ ...tab, aktif: url.startsWith(tab.href) }))} />;
}

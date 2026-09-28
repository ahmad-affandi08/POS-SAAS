import NavigasiTab from '@/Komponen/Pengelola/NavigasiTab';

export const daftarTab = [
    { label: 'Versi aplikasi', href: '/rilis' },
    { label: 'Flag fitur', href: '/flag-fitur' },
    { label: 'Kompatibilitas perangkat', href: '/kompatibilitas-perangkat' },
];

/** Navigasi antar halaman rilis aplikasi P-10 (versi & rollout, flag fitur/kill switch, HCL). */
export default function TabRilis() {
    return <NavigasiTab label="Rilis aplikasi" daftarTab={daftarTab} />;
}

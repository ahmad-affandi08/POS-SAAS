import NavigasiTab from '@/Komponen/Pengelola/NavigasiTab';

const daftarTab = [
    { label: 'Halaman', href: '/situs/halaman' },
    { label: 'Pengaturan', href: '/situs/pengaturan' },
    { label: 'Gambar', href: '/situs/gambar' },
];

/** Navigasi antar bagian konsol situs pemasaran (D-21). */
export default function TabSitus() {
    return <NavigasiTab label="Situs pemasaran" daftarTab={daftarTab} />;
}

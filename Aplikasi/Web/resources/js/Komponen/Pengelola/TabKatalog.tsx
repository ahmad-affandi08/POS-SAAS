import NavigasiTab from '@/Komponen/Pengelola/NavigasiTab';

const daftarTab = [
    { label: 'Paket', href: '/katalog/paket' },
    { label: 'Add-on', href: '/katalog/add-on' },
    { label: 'Kupon', href: '/katalog/kupon' },
    { label: 'Fitur', href: '/katalog/fitur' },
];

/** Navigasi antar data katalog P-04. */
export default function TabKatalog() {
    return <NavigasiTab label="Katalog" daftarTab={daftarTab} />;
}

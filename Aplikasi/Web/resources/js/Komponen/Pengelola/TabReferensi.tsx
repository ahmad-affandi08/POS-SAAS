import NavigasiTab from '@/Komponen/Pengelola/NavigasiTab';

const daftarTab = [
    { label: 'Tarif pajak', href: '/referensi/tarif-pajak' },
    { label: 'Hari libur', href: '/referensi/hari-libur' },
    { label: 'Wilayah', href: '/referensi/wilayah' },
    { label: 'Pembayaran', href: '/referensi/bank' },
    { label: 'Satuan', href: '/referensi/satuan' },
];

/** Navigasi antar data referensi P-02. */
export default function TabReferensi() {
    return <NavigasiTab label="Data referensi" daftarTab={daftarTab} />;
}

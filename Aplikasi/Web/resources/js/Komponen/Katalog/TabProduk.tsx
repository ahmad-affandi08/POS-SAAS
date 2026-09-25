import TabTautan from '@/Komponen/Navigasi/TabTautan';
import type { TabProduk as DataTabProduk } from '@/Tipe/Katalog';

type PropsTabProduk = { tab: DataTabProduk[]; aktif: DataTabProduk['Kunci'] };

/** Navigasi antar-halaman satu produk (Ringkasan, Harga, Pilihan, Resep, Komponen) dari `KepalaProduk.Tab`. */
export default function TabProduk({ tab, aktif }: PropsTabProduk) {
    if (tab.length <= 1) {
        return null;
    }

    return (
        <TabTautan
            label="Bagian produk"
            tab={tab.map((item) => ({ label: item.Label, href: item.Tautan, aktif: item.Kunci === aktif }))}
        />
    );
}

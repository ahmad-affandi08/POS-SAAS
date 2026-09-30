import TabTautan from '@/Komponen/Navigasi/TabTautan';

const alamatKartuStok = '/kelola/persediaan/kartu-stok';
const alamatNomorSeri = `${alamatKartuStok}/nomor-seri`;

type PropsTabKartuStok = {
    aktif: 'produk' | 'nomor-seri';
    /** Produk yang sedang dilihat; dibawa ke tab lain supaya pindah tab tidak mengulang pilihan. */
    uuidProduk?: string | null;
};

/**
 * Dua cara menelusuri riwayat barang, satu rumah menu (D-27, entri "Kartu stok"): per produk (kartu stok) dan per unit
 * (riwayat nomor seri/IMEI). Tab, bukan entri menu sendiri, karena grup Persediaan sudah di batas tujuh sub-menu.
 */
export default function TabKartuStok({ aktif, uuidProduk }: PropsTabKartuStok) {
    const query = uuidProduk ? `?${new URLSearchParams({ produk: uuidProduk }).toString()}` : '';

    return (
        <TabTautan
            label="Cara menelusuri riwayat stok"
            tab={[
                { label: 'Per produk', href: `${alamatKartuStok}${query}`, aktif: aktif === 'produk' },
                { label: 'Per nomor seri / IMEI', href: `${alamatNomorSeri}${query}`, aktif: aktif === 'nomor-seri' },
            ]}
        />
    );
}

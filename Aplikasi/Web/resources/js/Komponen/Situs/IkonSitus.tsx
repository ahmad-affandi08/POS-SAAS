import IkonNavigasi, { type NamaIkonNavigasi } from '@/Komponen/Navigasi/IkonNavigasi';

/** Ikon yang boleh dipilih di konsol (kuncinya tetap sama dengan `SkemaBagianSitus::IKON`). */
export const DaftarIkonSitus: Record<string, NamaIkonNavigasi> = {
    WifiOff: 'Retur',
    Receipt: 'Struk',
    Package: 'Produk',
    ChartColumn: 'Grafik',
    Users: 'Pelanggan',
    Store: 'Toko',
    Coffee: 'Toko',
    Scissors: 'Karyawan',
    Shirt: 'Paket',
    Truck: 'Pengiriman',
    ShieldCheck: 'Keamanan',
    Smartphone: 'Kasir',
    Printer: 'Struk',
    Tag: 'Diskon',
    Gift: 'Loyalitas',
    Wallet: 'Dompet',
    Calculator: 'Akuntansi',
    Clock: 'Kalender',
    Cloud: 'Lapisan',
    Headphones: 'Dukungan',
    Zap: 'Grafik',
    BadgeCheck: 'DaftarPeriksa',
    QrCode: 'KodeQr',
    UtensilsCrossed: 'Toko',
    ShoppingBasket: 'Pembelian',
    Building: 'Toko',
    ChartLine: 'Laporan',
    Percent: 'Diskon',
    Star: 'Loyalitas',
    Heart: 'Loyalitas',
    Sparkles: 'Loyalitas',
    Monitor: 'Kasir',
    CreditCard: 'Pembayaran',
    Boxes: 'Gudang',
    ClipboardList: 'DaftarPeriksa',
    WashingMachine: 'Toko',
    Warehouse: 'Gudang',
    Globe: 'Lokasi',
    Lock: 'Keamanan',
    RefreshCw: 'Retur',
    MessageCircle: 'Dukungan',
    Bell: 'Notifikasi',
    FileText: 'Laporan',
    Landmark: 'Akuntansi',
    HandCoins: 'Dompet',
    ChefHat: 'Toko',
    Pill: 'Produk',
    Car: 'Pengiriman',
};

export default function IkonSitus({ nama, className }: { nama: string | null; className?: string }) {
    const ikon = nama ? DaftarIkonSitus[nama] : undefined;

    return ikon ? <IkonNavigasi nama={ikon} className={className ?? 'size-6'} /> : null;
}

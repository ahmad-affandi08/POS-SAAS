import Akuntansi from '@/Aset/IkonNavigasi/Akuntansi.webp';
import Beranda from '@/Aset/IkonNavigasi/Beranda.webp';
import DaftarPeriksa from '@/Aset/IkonNavigasi/DaftarPeriksa.webp';
import Diskon from '@/Aset/IkonNavigasi/Diskon.webp';
import Dompet from '@/Aset/IkonNavigasi/Dompet.webp';
import Dukungan from '@/Aset/IkonNavigasi/Dukungan.webp';
import Grafik from '@/Aset/IkonNavigasi/Grafik.webp';
import Gudang from '@/Aset/IkonNavigasi/Gudang.webp';
import Kalender from '@/Aset/IkonNavigasi/Kalender.webp';
import Karyawan from '@/Aset/IkonNavigasi/Karyawan.webp';
import Kasir from '@/Aset/IkonNavigasi/Kasir.webp';
import Keamanan from '@/Aset/IkonNavigasi/Keamanan.webp';
import KodeQr from '@/Aset/IkonNavigasi/KodeQr.webp';
import KotakMasuk from '@/Aset/IkonNavigasi/KotakMasuk.webp';
import Laporan from '@/Aset/IkonNavigasi/Laporan.webp';
import Lapisan from '@/Aset/IkonNavigasi/Lapisan.webp';
import Lokasi from '@/Aset/IkonNavigasi/Lokasi.webp';
import Loyalitas from '@/Aset/IkonNavigasi/Loyalitas.webp';
import Notifikasi from '@/Aset/IkonNavigasi/Notifikasi.webp';
import Paket from '@/Aset/IkonNavigasi/Paket.webp';
import Pelanggan from '@/Aset/IkonNavigasi/Pelanggan.webp';
import Pembayaran from '@/Aset/IkonNavigasi/Pembayaran.webp';
import Pembelian from '@/Aset/IkonNavigasi/Pembelian.webp';
import Pengaturan from '@/Aset/IkonNavigasi/Pengaturan.webp';
import Pengiriman from '@/Aset/IkonNavigasi/Pengiriman.webp';
import Produk from '@/Aset/IkonNavigasi/Produk.webp';
import Retur from '@/Aset/IkonNavigasi/Retur.webp';
import Struk from '@/Aset/IkonNavigasi/Struk.webp';
import Tagihan from '@/Aset/IkonNavigasi/Tagihan.webp';
import Toko from '@/Aset/IkonNavigasi/Toko.webp';
import { cn } from '@/Komponen/Ui/utils';

const sumberIkon = {
    Akuntansi,
    Beranda,
    DaftarPeriksa,
    Diskon,
    Dompet,
    Dukungan,
    Grafik,
    Gudang,
    Kalender,
    Karyawan,
    Kasir,
    Keamanan,
    KodeQr,
    KotakMasuk,
    Laporan,
    Lapisan,
    Lokasi,
    Loyalitas,
    Notifikasi,
    Paket,
    Pelanggan,
    Pembayaran,
    Pembelian,
    Pengaturan,
    Pengiriman,
    Produk,
    Retur,
    Struk,
    Tagihan,
    Toko,
} as const;

export type NamaIkonNavigasi = keyof typeof sumberIkon;

export default function IkonNavigasi({ nama, className }: { nama: NamaIkonNavigasi; className?: string }) {
    return (
        <img
            src={sumberIkon[nama]}
            alt=""
            aria-hidden="true"
            draggable={false}
            width={24}
            height={24}
            data-ikon-navigasi={nama}
            className={cn('size-6 shrink-0 object-contain', className)}
        />
    );
}

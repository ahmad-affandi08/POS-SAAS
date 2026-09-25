import type { ReactNode } from 'react';

import gambarAkuntansi from '@/Aset/KeadaanKosong/AkuntansiKosong.svg';
import gambarLaporan from '@/Aset/KeadaanKosong/LaporanKosong.svg';
import gambarOutlet from '@/Aset/KeadaanKosong/OutletKosong.svg';
import gambarPelanggan from '@/Aset/KeadaanKosong/PelangganKosong.svg';
import gambarPembelian from '@/Aset/KeadaanKosong/PembelianKosong.svg';
import gambarPenjualan from '@/Aset/KeadaanKosong/PenjualanKosong.svg';
import gambarProduk from '@/Aset/KeadaanKosong/ProdukKosong.svg';
import gambarPromo from '@/Aset/KeadaanKosong/PromoKosong.svg';
import gambarShift from '@/Aset/KeadaanKosong/ShiftKosong.svg';
import gambarStok from '@/Aset/KeadaanKosong/StokKosong.svg';
import { Empty, EmptyContent, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Komponen/Ui/empty';
import { cn } from '@/Komponen/Ui/utils';

/** Subjek ilustrasi keadaan kosong (D-18, aset di `Aset/KeadaanKosong/`). */
export type JenisIlustrasiKosong =
    | 'Produk'
    | 'Penjualan'
    | 'Stok'
    | 'Pembelian'
    | 'Laporan'
    | 'Akuntansi'
    | 'Pelanggan'
    | 'Promo'
    | 'Outlet'
    | 'Shift';

const gambarIlustrasi: Record<JenisIlustrasiKosong, string> = {
    Produk: gambarProduk,
    Penjualan: gambarPenjualan,
    Stok: gambarStok,
    Pembelian: gambarPembelian,
    Laporan: gambarLaporan,
    Akuntansi: gambarAkuntansi,
    Pelanggan: gambarPelanggan,
    Promo: gambarPromo,
    Outlet: gambarOutlet,
    Shift: gambarShift,
};

type PropsKeadaanKosong = {
    judul: string;
    ilustrasi?: JenisIlustrasiKosong | undefined;
    /** `false` bila sudah di dalam wadah berbingkai (misal badan `TabelData`): tanpa garis & latar sendiri. */
    bingkai?: boolean;
    children?: ReactNode;
};

/**
 * Keadaan kosong (PRD §17.6.6): kalimat yang menjelaskan langkah berikutnya, plus tombol/tautan aksi. Dengan
 * `ilustrasi` (D-18), daftar utama yang belum berisi menampilkan ilustrasi datar di tengah; tanpa itu (misal hasil
 * cari/saring kosong) tetap ringkas rata kiri.
 */
export default function KeadaanKosong({ judul, ilustrasi, bingkai = true, children }: PropsKeadaanKosong) {
    const tengah = ilustrasi !== undefined;

    return (
        <Empty
            className={cn(
                'gap-3 px-4 py-6 md:p-6',
                bingkai ? 'rounded-panel border border-solid border-garis bg-card' : 'border-0',
                tengah && 'py-10 md:py-12',
                tengah ? 'items-center text-center' : 'items-start text-left',
            )}
        >
            <EmptyHeader className={cn('max-w-none', tengah ? 'items-center text-center' : 'items-start text-left')}>
                {ilustrasi ? (
                    <EmptyMedia>
                        <img
                            src={gambarIlustrasi[ilustrasi]}
                            alt=""
                            aria-hidden="true"
                            className="size-40 md:size-48"
                            draggable={false}
                        />
                    </EmptyMedia>
                ) : null}
                <EmptyTitle className="text-isi font-semibold tracking-normal text-teks-utama">{judul}</EmptyTitle>
            </EmptyHeader>
            {children ? (
                <EmptyContent
                    className={cn(
                        'max-w-none flex-row flex-wrap items-center gap-2 text-isi text-teks-sekunder',
                        tengah && 'justify-center',
                    )}
                >
                    {children}
                </EmptyContent>
            ) : null}
        </Empty>
    );
}

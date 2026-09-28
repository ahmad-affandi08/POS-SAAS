import type { ReactNode } from 'react';

import gambarKosong from '@/Aset/KeadaanKosong/Kosong.webp';
import { Empty, EmptyContent, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Komponen/Ui/empty';
import { cn } from '@/Komponen/Ui/utils';

type PropsKeadaanKosong = {
    judul: string;
    /**
     * `true` untuk daftar utama yang belum berisi data (D-18): ilustrasi di tengah.
     * Biarkan kosong untuk hasil cari/saring yang nihil — itu keadaan lain, dan ilustrasi besar di sana justru
     * membuat pengguna mengira datanya hilang.
     */
    ilustrasi?: boolean | undefined;
    /** `false` bila sudah di dalam wadah berbingkai (misal badan `TabelData`): tanpa garis & latar sendiri. */
    bingkai?: boolean;
    children?: ReactNode;
};

/**
 * Keadaan kosong (PRD §17.6.6): kalimat yang menjelaskan langkah berikutnya, plus tombol/tautan aksi.
 *
 * **Satu ilustrasi untuk semua daftar** (D-18 revisi pemilik produk). Sebelumnya ada sepuluh ilustrasi per subjek
 * (Produk, Penjualan, Stok, …), dan akibatnya keadaan kosong terasa berbeda-beda antar modul — sementara 19 daftar
 * utama tidak kebagian ilustrasi sama sekali karena subjeknya belum punya aset. Satu ilustrasi netral menghapus
 * kedua masalah itu sekaligus: seragam di mana pun, dan tidak ada daftar yang tertinggal karena menunggu aset baru.
 */
export default function KeadaanKosong({ judul, ilustrasi = false, bingkai = true, children }: PropsKeadaanKosong) {
    return (
        <Empty
            className={cn(
                'gap-3 px-4 py-6 md:p-6',
                bingkai ? 'rounded-panel border border-solid border-garis bg-card' : 'border-0',
                ilustrasi && 'py-10 md:py-12',
                ilustrasi ? 'items-center text-center' : 'items-start text-left',
            )}
        >
            <EmptyHeader className={cn('max-w-none', ilustrasi ? 'items-center text-center' : 'items-start text-left')}>
                {ilustrasi ? (
                    <EmptyMedia>
                        <img
                            src={gambarKosong}
                            alt=""
                            aria-hidden="true"
                            width={1254}
                            height={1254}
                            loading="lazy"
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
                        ilustrasi && 'justify-center',
                    )}
                >
                    {children}
                </EmptyContent>
            ) : null}
        </Empty>
    );
}

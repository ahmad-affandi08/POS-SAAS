import { useId, type ReactNode } from 'react';

import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { cn } from '@/Komponen/Ui/utils';

type PropsPanel =
    | {
          /** Judul bagian; dipakai sebagai nama region bagi pembaca layar. */
          judul: ReactNode;
          /** Id judul bila halaman perlu merujuknya; bawaan dibuat otomatis. */
          idJudul?: string;
          tingkat?: 'h2' | 'h3';
          keterangan?: ReactNode;
          /** Tombol/tautan di pojok kanan kepala panel. */
          aksi?: ReactNode;
          className?: string;
          children?: ReactNode;
      }
    // Tanpa judul: panel hanya jadi permukaan berpadding. `keterangan`/`aksi` sengaja tidak tersedia di sini,
    // karena keduanya tinggal di kepala panel dan akan hilang tanpa diketahui bila judulnya tidak ada.
    | { judul?: undefined; className?: string; children?: ReactNode };

/**
 * Panel bagian halaman back-office (D-28, §17.4.11). **Satu permukaan** untuk halaman kelola & konsol:
 * `rounded-panel` + tanpa bayangan (§17.6.11), padat untuk mode Ringkas.
 *
 * Masalah yang diperbaiki: pola panel ini ditulis ulang di tiga tempat dengan kelas yang berbeda-beda —
 * `PanelKatalog` (nama domain padahal dipakai 40 berkas di luar katalog), dan fungsi `Panel` lokal di
 * `Komponen/Laporan/DasborPemilik` serta `Halaman/Pengelola/Tenant/Tampil` yang lupa `rounded-panel`
 * & `shadow-none`. Akibatnya halaman berfungsi serupa punya radius dan elevasi berbeda, dan sebagian
 * tampak dibuat di periode desain yang lain.
 *
 * Judul panel memakai `text-subjudul font-semibold` dan menjadi nama region, jadi pembaca layar bisa
 * melompat antar bagian halaman. Pakai `tingkat="h3"` bila panel berada di bawah judul bagian lain.
 */
export default function Panel(props: PropsPanel) {
    const idOtomatis = useId();

    if (props.judul === undefined) {
        return <Card className={cn('gap-4 rounded-panel p-4 shadow-none', props.className)}>{props.children}</Card>;
    }

    const { judul, idJudul, tingkat = 'h2', keterangan, aksi, className, children } = props;
    const id = idJudul ?? `${idOtomatis}-judul`;
    const Judul = tingkat;

    return (
        <Card role="region" aria-labelledby={id} className={cn('gap-3 rounded-panel py-4 shadow-none', className)}>
            <CardHeader className="gap-1 px-4">
                <CardTitle>
                    <Judul id={id} className="text-subjudul font-semibold text-teks-utama">
                        {judul}
                    </Judul>
                </CardTitle>
                {keterangan ? (
                    <CardDescription className="text-keterangan text-teks-sekunder">{keterangan}</CardDescription>
                ) : null}
                {aksi ? <CardAction>{aksi}</CardAction> : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-3 px-4">{children}</CardContent>
        </Card>
    );
}

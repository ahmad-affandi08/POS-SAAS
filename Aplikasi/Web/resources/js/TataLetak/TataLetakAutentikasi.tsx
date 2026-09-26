import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { LogoMerek } from '@/Komponen/Merek/LogoMerek';
import { Card, CardContent } from '@/Komponen/Ui/card';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

import { PemberitahuanMelayang } from './BagianTataLetak';

type PropsTataLetak = { judul: string; keterangan?: string; children: ReactNode; lebar?: 'sempit' | 'sedang' };

/** Tata letak layar daftar, masuk, dan pemilih tenant (F-00). */
export default function TataLetakAutentikasi({ judul, keterangan, children, lebar = 'sempit' }: PropsTataLetak) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <>
            <Head title={judul} />
            <main
                className={`mx-auto flex min-h-screen w-full flex-col justify-center gap-6 px-4 py-10 ${
                    lebar === 'sempit' ? 'max-w-md' : 'max-w-2xl'
                }`}
            >
                <header className="flex flex-col gap-1">
                    {/* D-20: situs pemasaran bisa di domain lain, jadi tautan biasa (bukan kunjungan Inertia). */}
                    <a href={props.UrlPemasaran ?? '/'} className="mb-4 self-start">
                        <LogoMerek nama={props.NamaAplikasi} />
                    </a>
                    <h1 className="text-judul font-bold text-teks-utama">{judul}</h1>
                    {keterangan ? <p className="text-isi text-teks-sekunder">{keterangan}</p> : null}
                </header>
                {props.Kilat ? <Pemberitahuan jenis="info">{props.Kilat}</Pemberitahuan> : null}
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                <Card className="rounded-panel py-6 shadow-none">
                    <CardContent className="px-6">{children}</CardContent>
                </Card>
            </main>
            <PemberitahuanMelayang />
        </>
    );
}

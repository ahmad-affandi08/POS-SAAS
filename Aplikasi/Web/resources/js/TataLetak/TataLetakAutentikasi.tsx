import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

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
                    <Link href="/" className="text-label font-semibold text-teks-sekunder">
                        {props.NamaAplikasi}
                    </Link>
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

import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import PenandaLingkungan from '@/Komponen/Umpan/PenandaLingkungan';
import { Card, CardContent } from '@/Komponen/Ui/card';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

import { PemberitahuanMelayang } from './BagianTataLetak';

type PropsTataLetak = {
    judul: string;
    keterangan?: string;
    children: ReactNode;
};

/** Tata letak layar masuk, undangan, dan 2FA Platform Pengelola. */
export default function TataLetakAutentikasiPengelola({ judul, keterangan, children }: PropsTataLetak) {
    const { props } = usePage<PropsBersamaPengelola>();

    return (
        <>
            <Head title={judul} />
            <PenandaLingkungan lingkungan={props.Lingkungan} />
            <main className="mx-auto flex min-h-[calc(100vh-28px)] w-full max-w-md flex-col justify-center gap-6 px-4 py-10">
                <header className="flex flex-col gap-1">
                    <p className="text-label font-semibold text-teks-sekunder">
                        {props.NamaAplikasi} · Platform Pengelola
                    </p>
                    <h1 className="text-judul font-bold text-teks-utama">{judul}</h1>
                    {keterangan ? <p className="text-isi text-teks-sekunder">{keterangan}</p> : null}
                </header>
                {props.Kilat ? <Pemberitahuan jenis="info">{props.Kilat}</Pemberitahuan> : null}
                <Card className="rounded-panel py-6 shadow-none">
                    <CardContent className="px-6">{children}</CardContent>
                </Card>
            </main>
            <PemberitahuanMelayang />
        </>
    );
}

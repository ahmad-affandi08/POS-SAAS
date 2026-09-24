import { useId, type ReactNode } from 'react';

import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { cn } from '@/Komponen/Ui/utils';

type PropsPanelKatalog = {
    judul: ReactNode;
    /** Id judul bila halaman perlu merujuknya; bawaan dibuat otomatis. */
    idJudul?: string;
    tingkat?: 'h2' | 'h3';
    keterangan?: ReactNode;
    /** Tombol/tautan di pojok kanan kepala panel. */
    aksi?: ReactNode;
    className?: string;
    children?: ReactNode;
};

/**
 * Panel bagian halaman katalog (Card shadcn) dengan judul bertingkat sebagai nama region.
 * Padat untuk back-office (§17.6 mode Ringkas): tanpa bayangan dekoratif.
 */
export default function PanelKatalog({
    judul,
    idJudul,
    tingkat = 'h2',
    keterangan,
    aksi,
    className,
    children,
}: PropsPanelKatalog) {
    const idOtomatis = useId();
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

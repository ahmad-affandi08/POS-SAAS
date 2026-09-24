import type { ReactNode } from 'react';

import { Empty, EmptyContent, EmptyHeader, EmptyTitle } from '@/Komponen/Ui/empty';

type PropsKeadaanKosong = { judul: string; children?: ReactNode };

/** Keadaan kosong (PRD §17.6.6): kalimat yang menjelaskan langkah berikutnya, plus tombol/tautan aksi. */
export default function KeadaanKosong({ judul, children }: PropsKeadaanKosong) {
    return (
        <Empty className="items-start gap-3 rounded-panel border border-solid border-garis bg-card px-4 py-6 text-left md:p-6">
            <EmptyHeader className="max-w-none items-start text-left">
                <EmptyTitle className="text-isi font-semibold tracking-normal text-teks-utama">{judul}</EmptyTitle>
            </EmptyHeader>
            {children ? (
                <EmptyContent className="max-w-none flex-row flex-wrap items-center gap-2 text-isi text-teks-sekunder">
                    {children}
                </EmptyContent>
            ) : null}
        </Empty>
    );
}

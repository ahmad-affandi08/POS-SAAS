import type { ReactNode } from 'react';

import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Komponen/Ui/empty';

/** Keadaan kosong daftar data master (PRD §17.6.6): judul + langkah berikutnya, tanpa ilustrasi dekoratif. */
export default function KeadaanKosong({ judul, children }: { judul: string; children: ReactNode }) {
    return (
        <Empty role="status" className="border border-garis bg-permukaan md:p-8">
            <EmptyHeader>
                <EmptyTitle className="text-subjudul font-semibold text-teks-utama">{judul}</EmptyTitle>
                <EmptyDescription className="text-isi text-teks-sekunder">{children}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

import type { ReactNode } from 'react';

import { Card } from '@/Komponen/Ui/card';
import { Table, TableCaption } from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';

type PropsPanelTabel = {
    /** Keterangan tabel untuk pembaca layar (caption tersembunyi). */
    keterangan: string;
    children: ReactNode;
    className?: string;
};

/**
 * Tabel data master Platform Pengelola dalam panel: kepadatan Ringkas (§17.6), kepala kolom sekunder,
 * sel boleh membungkus teks panjang. Isi dengan `TableHeader`/`TableBody` dari `@/Komponen/Ui/table`.
 */
export default function PanelTabel({ keterangan, children, className }: PropsPanelTabel) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <Table
                className={cn(
                    'text-isi [&_td]:px-4 [&_td]:py-3 [&_td]:align-top [&_td]:whitespace-normal [&_th]:px-4 [&_th]:text-label [&_th]:font-semibold [&_th]:text-teks-sekunder',
                    className,
                )}
            >
                <TableCaption className="sr-only">{keterangan}</TableCaption>
                {children}
            </Table>
        </Card>
    );
}

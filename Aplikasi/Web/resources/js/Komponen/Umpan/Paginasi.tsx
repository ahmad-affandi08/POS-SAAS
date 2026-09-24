import { Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import { buttonVariants } from '@/Komponen/Ui/button';
import { Pagination, PaginationContent, PaginationItem } from '@/Komponen/Ui/pagination';

type PropsPaginasi = {
    alamat: string;
    saring: Record<string, string>;
    halamanSaatIni: number;
    halamanTerakhir: number;
    total: number;
    label: string;
};

const kelasTautan = buttonVariants({ variant: 'outline', size: 'sm', className: 'text-label font-semibold' });

/** Navigasi halaman memakai parameter query `halaman` (D-06). */
export default function Paginasi({ alamat, saring, halamanSaatIni, halamanTerakhir, total, label }: PropsPaginasi) {
    if (halamanTerakhir <= 1) {
        return null;
    }

    const BuatTautan = (halaman: number) =>
        `${alamat}?${new URLSearchParams({ ...saring, halaman: String(halaman) }).toString()}`;

    return (
        <Pagination aria-label={label} className="mx-0 flex-wrap items-center justify-between gap-2 text-label">
            <span className="text-teks-sekunder">
                Halaman {halamanSaatIni} dari {halamanTerakhir} · {total} entri
            </span>
            <PaginationContent className="gap-2">
                {halamanSaatIni > 1 ? (
                    <PaginationItem>
                        <Link href={BuatTautan(halamanSaatIni - 1)} rel="prev" className={kelasTautan}>
                            <ChevronLeftIcon aria-hidden="true" />
                            Sebelumnya
                        </Link>
                    </PaginationItem>
                ) : null}
                {halamanSaatIni < halamanTerakhir ? (
                    <PaginationItem>
                        <Link href={BuatTautan(halamanSaatIni + 1)} rel="next" className={kelasTautan}>
                            Berikutnya
                            <ChevronRightIcon aria-hidden="true" />
                        </Link>
                    </PaginationItem>
                ) : null}
            </PaginationContent>
        </Pagination>
    );
}

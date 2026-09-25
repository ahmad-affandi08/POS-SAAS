import { Link } from '@inertiajs/react';
import { DownloadIcon } from 'lucide-react';

import { Button } from '@/Komponen/Ui/button';
import { cn } from '@/Komponen/Ui/utils';
import { BuatQueryLaporan } from '@/Pustaka/Laporan';

type PropsNavigasiTab = {
    label: string;
    alamat: string;
    /** Saring halaman saat ini (tanpa `tab`). */
    query: Record<string, string>;
    tabAktif: string;
    tab: { nilai: string; label: string }[];
};

/**
 * Navigasi tab laporan sebagai tautan (keadaan di URL, bisa dibagikan): membawa saring halaman, membuang keadaan
 * tabel tab sebelumnya. Membungkus ke baris berikutnya di layar sempit (tanpa gulir horizontal halaman).
 */
export default function NavigasiTab({ label, alamat, query, tabAktif, tab }: PropsNavigasiTab) {
    return (
        <nav aria-label={label}>
            <ul className="flex flex-wrap gap-1 border-b border-garis">
                {tab.map((t) => {
                    const aktif = t.nilai === tabAktif;

                    return (
                        <li key={t.nilai}>
                            <Link
                                href={`${alamat}?${BuatQueryLaporan({ ...query, tab: t.nilai })}`}
                                preserveScroll
                                aria-current={aktif ? 'page' : undefined}
                                className={cn(
                                    '-mb-px inline-flex min-h-10 items-center border-b-2 px-3 text-isi pointer-coarse:min-h-11',
                                    aktif
                                        ? 'border-brand font-semibold text-teks-utama'
                                        : 'border-transparent text-teks-sekunder hover:text-teks-utama',
                                )}
                            >
                                {t.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

/** Tautan unduh CSV laporan sesuai saring halaman. */
export function TautanEkspor({
    alamat,
    query,
    label = 'Ekspor CSV',
}: {
    alamat: string;
    query: Record<string, string>;
    label?: string;
}) {
    const teksQuery = BuatQueryLaporan(query);

    return (
        <Button asChild variant="outline" size="sm">
            <a href={teksQuery === '' ? alamat : `${alamat}?${teksQuery}`}>
                <DownloadIcon aria-hidden="true" />
                {label}
            </a>
        </Button>
    );
}

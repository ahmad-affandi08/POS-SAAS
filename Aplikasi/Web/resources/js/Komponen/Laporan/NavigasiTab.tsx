import { DownloadIcon } from 'lucide-react';

import TabTautan from '@/Komponen/Navigasi/TabTautan';
import { Button } from '@/Komponen/Ui/button';
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
 * tabel tab sebelumnya. Gaya tab bersama `TabTautan`.
 */
export default function NavigasiTab({ label, alamat, query, tabAktif, tab }: PropsNavigasiTab) {
    return (
        <TabTautan
            label={label}
            pertahankanGulir
            tab={tab.map((t) => ({
                label: t.label,
                href: `${alamat}?${BuatQueryLaporan({ ...query, tab: t.nilai })}`,
                aktif: t.nilai === tabAktif,
            }))}
        />
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

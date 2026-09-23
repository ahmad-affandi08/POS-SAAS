import { Link } from '@inertiajs/react';

type PropsPaginasi = {
    alamat: string;
    saring: Record<string, string>;
    halamanSaatIni: number;
    halamanTerakhir: number;
    total: number;
    label: string;
};

/** Navigasi halaman memakai parameter query `halaman` (D-06). */
export default function Paginasi({ alamat, saring, halamanSaatIni, halamanTerakhir, total, label }: PropsPaginasi) {
    if (halamanTerakhir <= 1) {
        return null;
    }

    const BuatTautan = (halaman: number) =>
        `${alamat}?${new URLSearchParams({ ...saring, halaman: String(halaman) }).toString()}`;

    return (
        <nav aria-label={label} className="flex items-center justify-between text-label">
            <span className="text-teks-sekunder">
                Halaman {halamanSaatIni} dari {halamanTerakhir} · {total} entri
            </span>
            <div className="flex gap-3">
                {halamanSaatIni > 1 ? (
                    <Link href={BuatTautan(halamanSaatIni - 1)} className="font-semibold text-brand underline">
                        Sebelumnya
                    </Link>
                ) : null}
                {halamanSaatIni < halamanTerakhir ? (
                    <Link href={BuatTautan(halamanSaatIni + 1)} className="font-semibold text-brand underline">
                        Berikutnya
                    </Link>
                ) : null}
            </div>
        </nav>
    );
}

import { Link } from '@inertiajs/react';
import { useId } from 'react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import type { ItemLangkahBerikutnya } from '@/Tipe/PanduanAwal';

/** Yang belum selesai lebih dulu; urutan dari server dipertahankan di dalam tiap kelompok. */
export function UrutkanLangkahBerikutnya(daftar: ItemLangkahBerikutnya[]): ItemLangkahBerikutnya[] {
    return [...daftar.filter((item) => !item.Selesai), ...daftar.filter((item) => item.Selesai)];
}

/** Checklist "Langkah berikutnya" di Beranda (F-01 langkah 7). Daftar kosong = tidak ditampilkan. */
export default function DaftarLangkahBerikutnya({ daftar }: { daftar: ItemLangkahBerikutnya[] }) {
    const idJudul = useId();

    if (daftar.length === 0) {
        return null;
    }

    const jumlahSelesai = daftar.filter((item) => item.Selesai).length;

    return (
        <Card
            role="region"
            aria-labelledby={idJudul}
            className="gap-3 rounded-panel px-4 py-4 shadow-none sm:px-6 sm:py-6"
        >
            <CardHeader className="flex flex-wrap items-baseline justify-between gap-2 px-0 [.border-b]:pb-0">
                <CardTitle className="text-subjudul font-semibold text-teks-utama">
                    <h2 id={idJudul}>Langkah berikutnya</h2>
                </CardTitle>
                <CardDescription className="text-label text-teks-sekunder">
                    {String(jumlahSelesai)} dari {String(daftar.length)} selesai
                </CardDescription>
            </CardHeader>
            <CardContent className="px-0">
            <ul className="flex flex-col">
                {UrutkanLangkahBerikutnya(daftar).map((item) => (
                    <li
                        key={item.Kunci}
                        className="flex flex-col gap-1 border-b border-garis py-3 last:border-b-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                    >
                        <div className="flex min-w-0 flex-col gap-0.5">
                            <Link
                                href={item.Tautan}
                                className={`text-isi font-semibold break-words underline outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                                    item.Selesai ? 'text-teks-sekunder' : 'text-brand'
                                }`}
                            >
                                {item.Judul}
                            </Link>
                            <p className="text-label text-teks-sekunder">{item.Keterangan}</p>
                        </div>
                        <div className="shrink-0">
                            {item.Selesai ? (
                                <LabelStatus jenis="sukses" teks="Selesai" />
                            ) : (
                                <LabelStatus jenis="netral" teks="Belum" />
                            )}
                        </div>
                    </li>
                ))}
            </ul>
            </CardContent>
        </Card>
    );
}

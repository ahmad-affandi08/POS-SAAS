import { Link } from '@inertiajs/react';

import type { LangkahPanduan, RingkasanLangkah, StatusLangkahPanduan } from '@/Tipe/PanduanAwal';

/** Teks status langkah; status tidak pernah hanya warna (PRD §17.6.3). */
export const teksStatusLangkah: Record<StatusLangkahPanduan, string> = {
    Selesai: 'Selesai',
    Dilewati: 'Dilewati',
    Belum: 'Belum',
};

const kelasStatus: Record<StatusLangkahPanduan, string> = {
    Selesai: 'text-sukses',
    Dilewati: 'text-peringatan',
    Belum: 'text-teks-sekunder',
};

type PropsIndikatorLangkah = {
    langkah: RingkasanLangkah[];
    /** Langkah yang sedang dibuka; null di halaman ringkasan. */
    aktif: LangkahPanduan | null;
};

/** Penanda 6 langkah panduan awal. Setiap langkah bisa dibuka kapan saja (lewati & lanjutkan nanti). */
export default function IndikatorLangkah({ langkah, aktif }: PropsIndikatorLangkah) {
    const indeksAktif = langkah.findIndex((item) => item.Kunci === aktif);
    const jumlahSelesai = langkah.filter((item) => item.Status === 'Selesai').length;

    return (
        <nav aria-label="Langkah panduan awal" className="flex flex-col gap-2">
            <p className="text-label text-teks-sekunder">
                {indeksAktif >= 0
                    ? `Langkah ${String(indeksAktif + 1)} dari ${String(langkah.length)} · `
                    : ''}
                {`${String(jumlahSelesai)} dari ${String(langkah.length)} langkah selesai`}
            </p>
            <ol className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                {langkah.map((item, indeks) => {
                    const sedangDibuka = item.Kunci === aktif;

                    return (
                        <li key={item.Kunci}>
                            <Link
                                href={item.Tautan}
                                aria-current={sedangDibuka ? 'step' : undefined}
                                className={`flex h-full flex-col gap-0.5 rounded-kontrol border bg-permukaan px-3 py-2 outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                                    sedangDibuka ? 'border-b-4 border-brand' : 'border-garis'
                                }`}
                            >
                                <span className="text-label font-semibold break-words text-teks-utama">
                                    {String(indeks + 1)}. {item.Judul}
                                </span>
                                <span className={`text-keterangan font-semibold ${kelasStatus[item.Status]}`}>
                                    {sedangDibuka ? 'Sedang dibuka · ' : ''}
                                    {teksStatusLangkah[item.Status]}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

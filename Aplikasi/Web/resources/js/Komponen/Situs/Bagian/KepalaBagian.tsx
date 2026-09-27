import type { ReactNode } from 'react';

import { cn } from '@/Komponen/Ui/utils';

/** Latar satu bagian. `navy`/`merek` adalah jeda gelap yang memutus deretan bagian terang (D-25). */
export type LatarBagian = 'permukaan' | 'latar' | 'navy' | 'merek';

/** Bagian berlatar gelap: teks dibalik menjadi terang. */
export function CekGelap(latar: LatarBagian): boolean {
    return latar === 'navy' || latar === 'merek';
}

type PropsKepalaBagian = {
    label?: string | null;
    judul?: string | null;
    subjudul?: string | null;
    rata?: 'tengah' | 'kiri';
    gelap?: boolean;
    /** Garis Aksen di atas kepala bagian sebagai penanda (pengganti kotak ikon, D-25). */
    aksen?: boolean;
};

/**
 * Label kecil, judul bagian (h2), dan pengantar. Tidak merender apa pun bila ketiganya kosong.
 *
 * Bawaan **rata kiri** (D-25): rata tengah dipakai hanya bila bagian itu memang perlu dibaca sebagai
 * pengumuman (mis. ajakan penutup), supaya halaman tidak menjadi dinding teks rata tengah.
 */
export function KepalaBagian({
    label,
    judul,
    subjudul,
    rata = 'kiri',
    gelap = false,
    aksen = true,
}: PropsKepalaBagian) {
    if (!label && !judul && !subjudul) {
        return null;
    }

    return (
        <div
            className={cn(
                'mb-10 flex max-w-3xl flex-col gap-3',
                rata === 'tengah' ? 'mx-auto items-center text-center' : '',
            )}
        >
            {aksen ? <span className="h-[3px] w-10 shrink-0 rounded-full bg-aksen" aria-hidden /> : null}
            {label ? (
                <p
                    className={cn(
                        'text-label font-semibold tracking-wide uppercase',
                        gelap ? 'text-brand-gelap-teks' : 'text-teks-sekunder',
                    )}
                >
                    {label}
                </p>
            ) : null}
            {judul ? (
                <h2
                    className={cn(
                        'text-judul-bagian-hp font-bold sm:text-judul-bagian',
                        gelap ? 'text-permukaan' : 'text-teks-utama',
                    )}
                >
                    {judul}
                </h2>
            ) : null}
            {subjudul ? (
                <p
                    className={cn(
                        'text-pengantar whitespace-pre-line',
                        gelap ? 'text-brand-gelap-teks' : 'text-teks-sekunder',
                    )}
                >
                    {subjudul}
                </p>
            ) : null}
        </div>
    );
}

type PropsWadahBagian = {
    children: ReactNode;
    latar?: LatarBagian;
    id?: string;
    sempit?: boolean;
    /** Garis 1px pemisah, dipakai bila bagian sebelumnya juga terang (beda `latar`/`permukaan` hampir tak terlihat). */
    garisAtas?: boolean | undefined;
};

const KELAS_LATAR: Record<LatarBagian, string> = {
    permukaan: 'bg-permukaan',
    latar: 'bg-latar',
    navy: 'bg-teks-utama',
    merek: 'bg-brand-gelap',
};

/** Pembungkus satu bagian: latar, jarak vertikal, lebar isi seragam, dan animasi masuk (D-25). */
export function WadahBagian({
    children,
    latar = 'permukaan',
    id,
    sempit = false,
    garisAtas = false,
}: PropsWadahBagian) {
    return (
        <section id={id} className={cn('py-14 sm:py-20', KELAS_LATAR[latar], garisAtas && 'border-t border-garis')}>
            <div className={cn('muncul-saat-gulir mx-auto px-4', sempit ? 'max-w-3xl' : 'max-w-6xl')}>{children}</div>
        </section>
    );
}

/**
 * Kelas kartu di dalam bagian. Bingkai hanya dipakai bila kartu benar-benar bisa diklik atau perlu
 * dipisahkan dari latar; di bagian gelap kartu memakai garis merek agar tetap terbaca.
 */
export function KelasKartu(latar: LatarBagian, dapatDiklik = false): string {
    if (CekGelap(latar)) {
        return cn('rounded-panel border border-brand-gelap-garis p-6', dapatDiklik && 'hover:border-brand-gelap-teks');
    }

    return cn(
        'rounded-panel border border-garis p-6',
        latar === 'latar' ? 'bg-permukaan' : 'bg-latar',
        dapatDiklik && 'hover:border-brand',
    );
}

/** Gambar situs dengan dimensi asli (mengurangi pergeseran tata letak) dan muat malas. */
export function GambarBagian({
    gambar,
    className,
    prioritas = false,
}: {
    gambar: { Url: string; Alt: string; Lebar: number | null; Tinggi: number | null };
    className?: string;
    prioritas?: boolean;
}) {
    return (
        <img
            src={gambar.Url}
            alt={gambar.Alt}
            width={gambar.Lebar ?? undefined}
            height={gambar.Tinggi ?? undefined}
            loading={prioritas ? 'eager' : 'lazy'}
            decoding="async"
            className={className}
        />
    );
}

import type { ReactNode } from 'react';

import { cn } from '@/Komponen/Ui/utils';

type PropsKepalaBagian = {
    label?: string | null;
    judul?: string | null;
    subjudul?: string | null;
    rata?: 'tengah' | 'kiri';
    gelap?: boolean;
};

/** Label kecil, judul bagian (h2), dan pengantar. Tidak merender apa pun bila ketiganya kosong. */
export function KepalaBagian({ label, judul, subjudul, rata = 'tengah', gelap = false }: PropsKepalaBagian) {
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
            {label ? (
                <p
                    className={cn(
                        'text-label font-semibold tracking-wide uppercase',
                        gelap ? 'text-brand-gelap-teks' : 'text-brand',
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

type PropsWadahBagian = { children: ReactNode; latar?: 'latar' | 'permukaan' | 'merek'; id?: string; sempit?: boolean };

/** Pembungkus satu bagian: jarak vertikal & lebar isi seragam. */
export function WadahBagian({ children, latar = 'latar', id, sempit = false }: PropsWadahBagian) {
    return (
        <section
            id={id}
            className={cn(
                'py-14 sm:py-20',
                latar === 'permukaan' && 'bg-permukaan',
                latar === 'latar' && 'bg-latar',
                latar === 'merek' && 'bg-brand-gelap',
            )}
        >
            <div className={cn('mx-auto px-4', sempit ? 'max-w-3xl' : 'max-w-6xl')}>{children}</div>
        </section>
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

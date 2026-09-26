import type { ReactNode } from 'react';

import { cn } from '@/Komponen/Ui/utils';

import TautanSitus from './TautanSitus';

type PropsTombolSitus = {
    href: string;
    children: ReactNode;
    varian?: 'utama' | 'kedua' | 'terang' | 'garis-terang';
    ukuran?: 'sedang' | 'besar';
    className?: string;
};

const KELAS_VARIAN = {
    utama: 'bg-brand text-brand-teks hover:bg-brand-gelap',
    kedua: 'border border-garis-input bg-permukaan text-teks-utama hover:bg-permukaan-sorot',
    terang: 'bg-permukaan text-brand-gelap hover:bg-brand-lembut',
    'garis-terang': 'border border-brand-gelap-teks text-permukaan hover:bg-brand-gelap-sorot',
} as const;

/** Tombol ajakan situs pemasaran (D-21); target sentuh ≥ 44px. */
export default function TombolSitus({
    href,
    children,
    varian = 'utama',
    ukuran = 'sedang',
    className,
}: PropsTombolSitus) {
    return (
        <TautanSitus
            href={href}
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-kontrol font-semibold transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                ukuran === 'besar' ? 'min-h-12 px-6 text-subjudul' : 'min-h-11 px-4 text-isi',
                KELAS_VARIAN[varian],
                className,
            )}
        >
            {children}
        </TautanSitus>
    );
}

import type { ButtonHTMLAttributes, ReactNode } from 'react';

import { Button } from '@/Komponen/Ui/button';
import { Spinner } from '@/Komponen/Ui/spinner';

type PropsTombol = {
    varian?: 'utama' | 'sekunder' | 'bahaya';
    memproses?: boolean;
    children: ReactNode;
} & Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className'>;

/** Varian shadcn/ui per makna: aksi utama = brand (default), sekunder = outline, hapus/void = destructive. */
const varianUi = { utama: 'default', sekunder: 'outline', bahaya: 'destructive' } as const;

/** Tombol dengan label kata kerja spesifik (PRD §17.6.7). Warna brand hanya untuk aksi utama. */
export default function Tombol({
    varian = 'utama',
    memproses = false,
    children,
    disabled,
    type,
    ...atribut
}: PropsTombol) {
    return (
        <Button
            type={type ?? 'button'}
            variant={varianUi[varian]}
            disabled={disabled === true || memproses}
            aria-busy={memproses || undefined}
            className="h-8 pointer-coarse:h-11 px-4 text-label font-semibold focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            {...atribut}
        >
            {memproses ? (
                <>
                    <Spinner role={undefined} aria-label={undefined} aria-hidden="true" />
                    Memproses…
                </>
            ) : (
                children
            )}
        </Button>
    );
}

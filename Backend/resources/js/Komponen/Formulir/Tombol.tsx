import type { ButtonHTMLAttributes, ReactNode } from 'react';

type PropsTombol = {
    varian?: 'utama' | 'sekunder' | 'bahaya';
    memproses?: boolean;
    children: ReactNode;
} & Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className'>;

const kelasVarian = {
    utama: 'bg-brand text-permukaan border-brand',
    sekunder: 'bg-permukaan text-teks-utama border-garis-input',
    bahaya: 'bg-permukaan text-bahaya border-bahaya',
} as const;

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
        <button
            type={type ?? 'button'}
            disabled={disabled === true || memproses}
            aria-busy={memproses || undefined}
            className={`inline-flex h-10 items-center justify-center rounded-kontrol border px-4 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 ${kelasVarian[varian]}`}
            {...atribut}
        >
            {memproses ? 'Memproses…' : children}
        </button>
    );
}

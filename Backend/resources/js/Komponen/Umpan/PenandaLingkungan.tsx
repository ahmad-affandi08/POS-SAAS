import type { LingkunganPengelola } from '@/Tipe/Pengelola';

const kelasLingkungan = {
    Produksi: 'bg-bahaya text-permukaan',
    Staging: 'bg-peringatan text-permukaan',
    Lokal: 'bg-info text-permukaan',
} as const;

const keteranganLingkungan = {
    Produksi: 'Produksi: perubahan berdampak ke tenant sungguhan',
    Staging: 'Staging: data uji',
    Lokal: 'Lokal: lingkungan pengembangan',
} as const;

/** Penanda lingkungan yang mencolok di bagian atas Platform Pengelola (PRD §13.8, §17.6.2). */
export default function PenandaLingkungan({ lingkungan }: { lingkungan: LingkunganPengelola }) {
    return (
        <div className={`px-4 py-1 text-center text-label font-semibold ${kelasLingkungan[lingkungan]}`}>
            {keteranganLingkungan[lingkungan]}
        </div>
    );
}

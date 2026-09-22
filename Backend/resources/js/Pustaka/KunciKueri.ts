/**
 * Pabrik key TanStack Query terpusat (PRD §17.4.2). Semua kueri wajib memakai key dari sini
 * agar invalidasi setelah mutasi Inertia konsisten.
 */
export const KunciKueri = {
    Perangkat: (idOutlet: string) => ['Perangkat', idOutlet] as const,
    Laporan: (nama: string, saring: Record<string, string>) => ['Laporan', nama, saring] as const,
} as const;

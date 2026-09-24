/**
 * Pabrik key TanStack Query terpusat (PRD §17.4.2). Semua kueri wajib memakai key dari sini
 * agar invalidasi setelah mutasi Inertia konsisten.
 */
export const KunciKueri = {
    Perangkat: (idOutlet: string) => ['Perangkat', idOutlet] as const,
    Laporan: (nama: string, saring: Record<string, string>) => ['Laporan', nama, saring] as const,
    // F-03: pemilih bahan/komponen (GET /kelola/produk/cari) dan polling status impor.
    Produk: {
        Cari: (kata: string, jenis: readonly string[]) => ['Produk', 'Cari', kata, [...jenis]] as const,
    },
    Impor: {
        Status: (uuid: string) => ['Impor', 'Status', uuid] as const,
    },
} as const;

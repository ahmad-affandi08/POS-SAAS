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
    // F-05a: pemilih produk stok awal (GET /kelola/persediaan/produk/cari), polling status posting & impor stok awal.
    Persediaan: {
        CariProduk: (kata: string, uuidGudang: string | null) =>
            ['Persediaan', 'CariProduk', kata, uuidGudang] as const,
        StatusPosting: (uuid: string) => ['Persediaan', 'StatusPosting', uuid] as const,
        StatusImpor: (uuid: string) => ['Persediaan', 'StatusImpor', uuid] as const,
    },
} as const;

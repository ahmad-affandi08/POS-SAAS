/**
 * Pabrik key TanStack Query terpusat (PRD §17.4.2). Semua kueri wajib memakai key dari sini
 * agar invalidasi setelah mutasi Inertia konsisten.
 */
export const KunciKueri = {
    // D-16: data TabelData mode server, per tabel & query URL ternormalisasi.
    Tabel: (id: string, alamat: string, query: string) => ['Tabel', id, alamat, query] as const,
    TabelSemua: (id: string) => ['Tabel', id] as const,
    Perangkat: (idOutlet: string) => ['Perangkat', idOutlet] as const,
    // Pencarian cepat di kepala halaman: hasil per sumber (produk, pelanggan, …) untuk satu kata.
    PencarianCepat: (idSumber: string, kata: string) => ['PencarianCepat', idSumber, kata] as const,
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
        // F-05b: batch & nomor seri tersedia per produk & lokasi (GET /kelola/persediaan/pelacakan).
        Pelacakan: (uuidProduk: string, uuidGudang: string) =>
            ['Persediaan', 'Pelacakan', uuidProduk, uuidGudang] as const,
    },
    // F-17 Self-Order QR Meja: harga keranjang dari server (web publik tidak menghitung harga), polling status pesanan
    // tamu, dan QR meja di back-office.
    PesanSendiri: {
        Hitung: (token: string, tanda: string) => ['PesanSendiri', 'Hitung', token, tanda] as const,
        Status: (token: string, uuid: string) => ['PesanSendiri', 'Status', token, uuid] as const,
        QrMeja: (uuidMeja: string) => ['PesanSendiri', 'QrMeja', uuidMeja] as const,
    },
    // P-10: dampak menaikkan versi minimum (BR-P10.2), dibaca saat dialog dibuka.
    Pengelola: {
        DampakVersiMinimum: (uuidRilis: string) => ['Pengelola', 'DampakVersiMinimum', uuidRilis] as const,
    },
} as const;

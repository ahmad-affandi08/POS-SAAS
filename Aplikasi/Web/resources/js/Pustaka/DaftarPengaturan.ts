import { IzinTenant, type KunciIzinTenant } from '@/Tipe/Organisasi';

/**
 * Isi halaman Pengaturan (`/kelola/pengaturan`).
 *
 * Pengaturan PAYOU tinggal di dalam modulnya masing-masing (Pengaturan kasir di menu Shift & kas, Pengaturan
 * persediaan di menu Persediaan, dan seterusnya) karena di situlah konteksnya. Halaman ini tidak memindahkannya,
 * hanya mengumpulkan tautannya di satu tempat supaya bisa ditemukan tanpa hafal letak menunya.
 *
 * `izin` null = semua anggota boleh. `fitur` = kunci fitur paket (D-23): di luar paket tetap tampil dengan gembok,
 * kliknya membuka dialog naik paket, sama seperti di menu samping.
 */
export type ButirPengaturan = {
    label: string;
    keterangan: string;
    href: string;
    izin: KunciIzinTenant | null;
    fitur?: string;
};

export type GrupPengaturan = { judul: string; butir: ButirPengaturan[] };

export const daftarPengaturan: GrupPengaturan[] = [
    {
        judul: 'Usaha',
        butir: [
            {
                label: 'Profil usaha',
                keterangan: 'Nama usaha, logo, alamat, kota, NPWP, dan status PKP. Tampil di struk dan aplikasi kasir.',
                href: '/kelola/pengaturan/profil-usaha',
                izin: IzinTenant.OutletKelola,
            },
            {
                label: 'Outlet & gudang',
                keterangan: 'Cabang, gudang, area & meja, zona waktu, jam tutup buku, dan profil pajak per outlet.',
                href: '/kelola/outlet',
                izin: IzinTenant.OutletLihat,
            },
            {
                label: 'Langganan & tagihan',
                keterangan: 'Paket yang aktif, batas pemakaian, tagihan, dan bukti pembayaran.',
                href: '/kelola/langganan',
                izin: IzinTenant.LanggananKelola,
            },
        ],
    },
    {
        judul: 'Kasir & struk',
        butir: [
            {
                label: 'Pengaturan kasir',
                keterangan: 'Mode kasir, kembalian, pembulatan, diskon, dan perilaku layar jual.',
                href: '/kelola/kasir/pengaturan',
                izin: IzinTenant.OutletKelola,
            },
            {
                label: 'Pengaturan struk',
                keterangan: 'Isi struk, catatan kaki, logo, dan cetak otomatis. Satu pengaturan untuk semua outlet.',
                href: '/kelola/kasir/struk',
                izin: IzinTenant.OutletKelola,
            },
            {
                label: 'Kategori kas',
                keterangan: 'Kategori kas masuk & keluar yang bisa dipilih kasir saat shift berjalan.',
                href: '/kelola/kasir/kategori-kas',
                izin: IzinTenant.AkuntansiKelola,
            },
            {
                label: 'Gerbang pembayaran',
                keterangan: 'Gerbang QRIS milik toko untuk pembayaran dinamis di kasir.',
                href: '/kelola/pembayaran/gerbang',
                izin: IzinTenant.PembayaranGerbangAtur,
            },
        ],
    },
    {
        judul: 'Stok & pembelian',
        butir: [
            {
                label: 'Pengaturan persediaan',
                keterangan: 'Metode harga pokok, stok minus, dan perilaku opname & penyesuaian.',
                href: '/kelola/persediaan/pengaturan',
                izin: IzinTenant.AkuntansiKelola,
            },
            {
                label: 'Pengaturan pembelian',
                keterangan: 'Persetujuan pesanan pembelian, toleransi penerimaan, dan draf PO otomatis.',
                href: '/kelola/pembelian/pengaturan',
                izin: IzinTenant.PembelianPoSetujui,
            },
        ],
    },
    {
        judul: 'Pelanggan',
        butir: [
            {
                label: 'Tier pelanggan',
                keterangan: 'Tingkatan pelanggan dan harga khusus per tier.',
                href: '/kelola/pelanggan/tier',
                izin: IzinTenant.PelangganLihat,
                fitur: 'pelanggan.loyalti',
            },
            {
                label: 'Pengaturan loyalti',
                keterangan: 'Perolehan poin, masa berlaku, dan penukaran poin sebagai diskon.',
                href: '/kelola/pelanggan/loyalti',
                izin: IzinTenant.PelangganLihat,
                fitur: 'pelanggan.loyalti',
            },
        ],
    },
    {
        judul: 'Akuntansi',
        butir: [
            {
                label: 'Bagan akun',
                keterangan: 'Daftar akun (COA) beserta nama dan statusnya.',
                href: '/kelola/akuntansi/akun',
                izin: IzinTenant.LaporanKeuanganLihat,
                fitur: 'akuntansi.penuh',
            },
            {
                label: 'Pemetaan akun',
                keterangan: 'Akun yang dipakai jurnal otomatis untuk penjualan, stok, pajak, dan kas.',
                href: '/kelola/akuntansi/pemetaan',
                izin: IzinTenant.LaporanKeuanganLihat,
                fitur: 'akuntansi.penuh',
            },
            {
                label: 'Tutup buku',
                keterangan: 'Kunci periode bulanan & tahunan, dan buka kunci bila perlu koreksi.',
                href: '/kelola/akuntansi/tutup-buku',
                izin: IzinTenant.LaporanKeuanganLihat,
                fitur: 'akuntansi.penuh',
            },
        ],
    },
    {
        judul: 'Akses & keamanan',
        butir: [
            {
                label: 'Pengguna & peran',
                keterangan: 'Anggota usaha, izin per peran, dan PIN kasir mereka.',
                href: '/kelola/pengguna',
                izin: IzinTenant.PenggunaLihat,
            },
            {
                label: 'Perangkat kasir',
                keterangan: 'Perangkat terdaftar, kode aktivasi, pencabutan, dan profil hardware.',
                href: '/kelola/perangkat',
                izin: IzinTenant.PerangkatLihat,
            },
            {
                label: 'Keamanan akun saya',
                keterangan: 'Kata sandi, verifikasi dua langkah, dan PIN kasir milik Anda sendiri.',
                href: '/kelola/keamanan',
                izin: null,
            },
            {
                label: 'Log audit',
                keterangan: 'Riwayat perubahan penting: siapa, apa, kapan, dari perangkat mana.',
                href: '/kelola/log-audit',
                izin: IzinTenant.AuditLihat,
            },
        ],
    },
];

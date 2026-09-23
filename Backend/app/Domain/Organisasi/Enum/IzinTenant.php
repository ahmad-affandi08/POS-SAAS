<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Izin tenant (PRD §19.1, format D-06 `{modul}.{objek}.{aksi}`). Izin F-02 dipakai perantara `WajibIzinTenant`.
 * Izin modul lain sudah didaftarkan agar peran bawaan & peran kustom stabil sejak awal; penegakannya dibangun
 * bersama flow masing-masing (F-03 produk, F-05 persediaan, F-07/F-09 penjualan, F-13 akuntansi, F-14 laporan,
 * F-19 langganan). Menambah izin baru = tambah case + sesuaikan `PeranTenantBawaan`.
 */
enum IzinTenant: string
{
    // F-02 Setup organisasi.
    case OutletLihat = 'outlet.lihat';
    case OutletKelola = 'outlet.kelola';
    case PenggunaLihat = 'pengguna.lihat';
    case PenggunaUndang = 'pengguna.undang';
    case PenggunaUbah = 'pengguna.ubah';
    case PenggunaNonaktifkan = 'pengguna.nonaktifkan';
    case PeranKelola = 'peran.kelola';
    case AuditLihat = 'audit.lihat';
    // F-02b Perangkat & PIN kasir.
    case PerangkatLihat = 'perangkat.lihat';
    case PerangkatKelola = 'perangkat.kelola';
    case PenggunaPinAtur = 'pengguna.pin.atur';

    // Flow berikutnya (§19.1 & §19.2); penegakan dibangun bersama flow-nya.
    case ProdukLihat = 'produk.lihat';
    case ProdukKelola = 'produk.kelola';
    case ProdukHargaUbah = 'produk.harga.ubah';
    case PersediaanLihat = 'persediaan.lihat';
    case PersediaanKelola = 'persediaan.kelola';
    case PersediaanPenyesuaianSetujui = 'persediaan.penyesuaian.setujui';
    case PembelianKelola = 'pembelian.kelola';
    case PenjualanBuat = 'penjualan.buat';
    case PenjualanVoid = 'penjualan.void';
    case PenjualanDiskonManual = 'penjualan.diskon.manual';
    case LaporanPenjualanLihat = 'laporan.penjualan.lihat';
    case LaporanKeuanganLihat = 'laporan.keuangan.lihat';
    case AkuntansiKelola = 'akuntansi.kelola';
    case LanggananKelola = 'langganan.kelola';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::OutletLihat => 'Melihat outlet, gudang, dan merek',
            self::OutletKelola => 'Menambah, mengubah, dan mengarsipkan outlet, gudang, dan merek',
            self::PenggunaLihat => 'Melihat daftar pengguna & peran',
            self::PenggunaUndang => 'Mengundang pengguna',
            self::PenggunaUbah => 'Mengubah peran & akses outlet pengguna',
            self::PenggunaNonaktifkan => 'Menonaktifkan & mengaktifkan kembali pengguna',
            self::PeranKelola => 'Membuat & mengubah peran kustom',
            self::AuditLihat => 'Melihat log audit',
            // F-02b
            self::PerangkatLihat => 'Melihat perangkat POS',
            self::PerangkatKelola => 'Menambah, mengaktifkan, dan mencabut perangkat POS',
            self::PenggunaPinAtur => 'Mengatur ulang PIN kasir anggota',
            self::ProdukLihat => 'Melihat produk',
            self::ProdukKelola => 'Mengelola produk',
            self::ProdukHargaUbah => 'Mengubah harga jual',
            self::PersediaanLihat => 'Melihat stok',
            self::PersediaanKelola => 'Penerimaan, transfer, opname, penyesuaian stok',
            self::PersediaanPenyesuaianSetujui => 'Menyetujui penyesuaian stok',
            self::PembelianKelola => 'Mengelola pemasok & pesanan pembelian',
            self::PenjualanBuat => 'Berjualan di POS (jual, bayar, simpan pesanan, shift sendiri)',
            self::PenjualanVoid => 'Membatalkan (void) transaksi',
            self::PenjualanDiskonManual => 'Memberi diskon manual',
            self::LaporanPenjualanLihat => 'Melihat laporan penjualan',
            self::LaporanKeuanganLihat => 'Melihat laporan keuangan',
            self::AkuntansiKelola => 'Mengelola jurnal, pajak, dan tutup buku',
            self::LanggananKelola => 'Mengelola langganan & tagihan',
        };
    }

    /** Kelompok tampilan di halaman peran (mengikuti menu back-office §17.6.5). */
    public function AmbilKelompok(): string
    {
        return match ($this) {
            self::OutletLihat, self::OutletKelola, self::PerangkatLihat, self::PerangkatKelola => 'Organisasi',
            self::PenggunaLihat, self::PenggunaUndang, self::PenggunaUbah, self::PenggunaNonaktifkan,
            self::PeranKelola, self::AuditLihat, self::PenggunaPinAtur => 'Pengguna & keamanan',
            self::ProdukLihat, self::ProdukKelola, self::ProdukHargaUbah => 'Produk',
            self::PersediaanLihat, self::PersediaanKelola, self::PersediaanPenyesuaianSetujui, self::PembelianKelola => 'Persediaan & pembelian',
            self::PenjualanBuat, self::PenjualanVoid, self::PenjualanDiskonManual => 'Penjualan',
            self::LaporanPenjualanLihat, self::LaporanKeuanganLihat, self::AkuntansiKelola => 'Keuangan & laporan',
            self::LanggananKelola => 'Langganan',
        };
    }

    /**
     * Izin yang hanya boleh dimiliki Owner (§19.1: Admin = semua kecuali langganan & kepemilikan). Tidak bisa
     * dimasukkan ke peran kustom.
     */
    public function CekKhususPemilik(): bool
    {
        return $this === self::LanggananKelola;
    }

    /**
     * @return list<string>
     */
    public static function AmbilSemuaKunci(): array
    {
        return array_map(fn (self $izin) => $izin->value, self::cases());
    }
}

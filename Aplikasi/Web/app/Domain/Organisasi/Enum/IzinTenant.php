<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Izin tenant (PRD §19.1, format D-06 `{modul}.{objek}.{aksi}`). Izin F-02 dipakai perantara `WajibIzinTenant`.
 * Izin modul lain sudah didaftarkan agar peran bawaan & peran kustom stabil sejak awal; penegakannya dibangun
 * bersama flow masing-masing (F-03 produk, F-05 persediaan, F-07/F-09 penjualan, F-13 akuntansi, F-14 laporan,
 * F-19 langganan). Menambah izin baru = tambah case + sesuaikan `PeranTenantBawaan`, lalu jalankan
 * `organisasi:siapkan-peran` agar peran bawaan tenant lama ikut menerimanya.
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
    // F-01 Panduan awal (onboarding wizard).
    case PanduanAwalKelola = 'panduan-awal.kelola';
    // F-08 v2.06: gerbang pembayaran QRIS dinamis milik tenant (akun merchant & kredensial; dana ke rekening tenant).
    case PembayaranGerbangAtur = 'pembayaran.gerbang.atur';

    // Flow berikutnya (§19.1 & §19.2); penegakan dibangun bersama flow-nya.
    case ProdukLihat = 'produk.lihat';
    case ProdukKelola = 'produk.kelola';
    case ProdukHargaUbah = 'produk.harga.ubah';
    case PersediaanLihat = 'persediaan.lihat';
    case PersediaanKelola = 'persediaan.kelola';
    case PersediaanPenyesuaianSetujui = 'persediaan.penyesuaian.setujui';
    // F-05a: posting & pembatalan stok awal (menulis jurnal ekuitas saldo awal).
    case PersediaanStokAwalPosting = 'persediaan.stok-awal.posting';
    case PembelianKelola = 'pembelian.kelola';
    // F-04 fase 1 (§19.2): menyetujui PO di atas `BatasPersetujuanPo` (bukan pembuatnya; bawaan Pemilik & Admin).
    case PembelianPoSetujui = 'pembelian.po.setujui';
    case PenjualanBuat = 'penjualan.buat';
    case PenjualanVoid = 'penjualan.void';
    case PenjualanDiskonManual = 'penjualan.diskon.manual';
    // F-06 BR-06.4: menyetujui kas keluar di atas batas dengan PIN (supervisor ke atas).
    case KasKeluarSetujui = 'kas.keluar.setujui';
    // F-07b BR-07.3: menyetujui diskon manual di atas batas kasir dengan PIN (supervisor ke atas).
    case PenjualanDiskonSetujui = 'penjualan.diskon.setujui';
    // F-12 BR-12.1: PIN penyetuju penjualan tempo di atas limit kredit / piutang lewat jatuh tempo.
    case PenjualanTempoSetujui = 'penjualan.tempo.setujui';
    // F-11: menyetujui selisih kas tutup shift di atas toleransi dengan PIN (supervisor ke atas).
    case ShiftSelisihSetujui = 'shift.selisih.setujui';
    case LaporanPenjualanLihat = 'laporan.penjualan.lihat';
    case LaporanKeuanganLihat = 'laporan.keuangan.lihat';
    case AkuntansiKelola = 'akuntansi.kelola';
    case LanggananKelola = 'langganan.kelola';

    // P-09 Bantuan (tiket dukungan ke tim platform).
    case BantuanTiketLihat = 'bantuan.tiket.lihat';
    case BantuanTiketKelola = 'bantuan.tiket.kelola';
    // F-16a CRM-01 pelanggan (back-office; memilih & membuat pelanggan di POS cukup `penjualan.buat`).
    case PelangganLihat = 'pelanggan.lihat';
    case PelangganKelola = 'pelanggan.kelola';
    // F-16d bagian 1: tarik/sesuaikan deposit pelanggan & batal isi deposit (uang pelanggan).
    case PelangganDepositKelola = 'pelanggan.deposit.kelola';
    // F-16d bagian 2: mengembalikan/menghanguskan sisa paket sesi pelanggan.
    case PelangganSesiKelola = 'pelanggan.sesi.kelola';
    // F-18: karyawan, jadwal kerja, rekap absensi.
    case KaryawanLihat = 'karyawan.lihat';
    case KaryawanKelola = 'karyawan.kelola';
    // v2.00 mode Pelayan: mencatat pesanan meja & mengirim ke dapur tanpa berjualan/menerima pembayaran.
    case PesananMejaCatat = 'pesanan.meja.catat';

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
            self::PanduanAwalKelola => 'Menjalankan panduan awal (profil usaha, template sektor, pajak, produk awal, metode pembayaran)',
            self::PembayaranGerbangAtur => 'Mengatur gerbang pembayaran QRIS dinamis (akun merchant & kredensial)',
            self::ProdukLihat => 'Melihat produk',
            self::ProdukKelola => 'Mengelola produk',
            self::ProdukHargaUbah => 'Mengubah harga jual',
            self::PersediaanLihat => 'Melihat stok',
            self::PersediaanKelola => 'Penerimaan, transfer, opname, penyesuaian stok',
            self::PersediaanPenyesuaianSetujui => 'Menyetujui penyesuaian stok',
            self::PersediaanStokAwalPosting => 'Memposting & membatalkan stok awal (jurnal ekuitas saldo awal)',
            self::PembelianKelola => 'Mengelola pemasok, pesanan pembelian, penerimaan, faktur, hutang, dan retur pembelian',
            self::PembelianPoSetujui => 'Menyetujui pesanan pembelian di atas batas persetujuan',
            self::PenjualanBuat => 'Berjualan di POS (jual, bayar, simpan pesanan, shift sendiri)',
            self::PenjualanVoid => 'Membatalkan (void) transaksi',
            self::PenjualanDiskonManual => 'Memberi diskon manual',
            self::KasKeluarSetujui => 'Menyetujui kas keluar di atas batas',
            self::PenjualanDiskonSetujui => 'Menyetujui diskon manual di atas batas kasir',
            self::PenjualanTempoSetujui => 'Menyetujui penjualan tempo di atas limit kredit atau saat piutang lewat jatuh tempo',
            self::ShiftSelisihSetujui => 'Menyetujui selisih kas tutup shift di atas toleransi',
            self::LaporanPenjualanLihat => 'Melihat laporan penjualan',
            self::LaporanKeuanganLihat => 'Melihat laporan keuangan',
            self::AkuntansiKelola => 'Mengelola jurnal, pajak, dan tutup buku',
            self::LanggananKelola => 'Mengelola langganan & tagihan',
            self::BantuanTiketLihat => 'Melihat tiket bantuan & balasan tim dukungan',
            self::BantuanTiketKelola => 'Membuat, membalas, dan menyelesaikan tiket bantuan',
            self::PelangganLihat => 'Melihat data & riwayat belanja pelanggan',
            self::PelangganKelola => 'Menambah, mengubah, dan mengarsipkan pelanggan',
            self::PelangganDepositKelola => 'Menarik, menyesuaikan, dan membatalkan isi deposit pelanggan',
            self::PelangganSesiKelola => 'Mengembalikan atau menghanguskan sisa paket sesi pelanggan',
            self::KaryawanLihat => 'Melihat karyawan, jadwal kerja, dan rekap absensi',
            self::KaryawanKelola => 'Mengelola data karyawan dan jadwal kerja',
            self::PesananMejaCatat => 'Mencatat pesanan meja & mengirim ke dapur tanpa menerima pembayaran (Pelayan)',
        };
    }

    /** Kelompok tampilan di halaman peran (mengikuti menu back-office §17.6.5). */
    public function AmbilKelompok(): string
    {
        return match ($this) {
            self::OutletLihat, self::OutletKelola, self::PerangkatLihat, self::PerangkatKelola, self::PanduanAwalKelola,
            self::PembayaranGerbangAtur => 'Organisasi',
            self::PenggunaLihat, self::PenggunaUndang, self::PenggunaUbah, self::PenggunaNonaktifkan,
            self::PeranKelola, self::AuditLihat, self::PenggunaPinAtur => 'Pengguna & keamanan',
            self::ProdukLihat, self::ProdukKelola, self::ProdukHargaUbah => 'Produk',
            self::PersediaanLihat, self::PersediaanKelola, self::PersediaanPenyesuaianSetujui, self::PersediaanStokAwalPosting,
            self::PembelianKelola, self::PembelianPoSetujui => 'Persediaan & pembelian',
            self::PenjualanBuat, self::PenjualanVoid, self::PenjualanDiskonManual, self::KasKeluarSetujui,
            self::PenjualanDiskonSetujui, self::PenjualanTempoSetujui, self::ShiftSelisihSetujui, self::PesananMejaCatat => 'Penjualan',
            self::LaporanPenjualanLihat, self::LaporanKeuanganLihat, self::AkuntansiKelola => 'Keuangan & laporan',
            self::LanggananKelola => 'Langganan',
            self::BantuanTiketLihat, self::BantuanTiketKelola => 'Bantuan',
            self::PelangganLihat, self::PelangganKelola, self::PelangganDepositKelola, self::PelangganSesiKelola => 'Pelanggan',
            self::KaryawanLihat, self::KaryawanKelola => 'Karyawan',
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

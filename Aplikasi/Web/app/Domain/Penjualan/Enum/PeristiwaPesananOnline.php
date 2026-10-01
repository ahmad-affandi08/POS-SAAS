<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * F-17 bagian 3 (v3.32): peristiwa pesanan toko online yang diberitahukan ke pembeli lewat WhatsApp. Hanya yang
 * mengubah apa yang harus pembeli lakukan atau tunggu; perpindahan internal toko (Diproses, Dikemas) tidak dikirim.
 */
enum PeristiwaPesananOnline: string
{
    case PembayaranDiterima = 'PembayaranDiterima';
    case Dikonfirmasi = 'Dikonfirmasi';
    case SiapDiambil = 'SiapDiambil';
    case Dikirim = 'Dikirim';
    case Ditolak = 'Ditolak';
    case Dibatalkan = 'Dibatalkan';
    case Kedaluwarsa = 'Kedaluwarsa';

    /** Kalimat setelah "Pesanan {Nomor} di {Toko} …". */
    public function AmbilKalimat(): string
    {
        return match ($this) {
            self::PembayaranDiterima => 'sudah kami terima pembayarannya dan sedang menunggu konfirmasi toko.',
            self::Dikonfirmasi => 'sudah dikonfirmasi dan sedang disiapkan.',
            self::SiapDiambil => 'sudah siap diambil.',
            self::Dikirim => 'sedang dalam perjalanan ke alamat Anda.',
            self::Ditolak => 'tidak dapat diproses oleh toko.',
            self::Dibatalkan => 'dibatalkan.',
            self::Kedaluwarsa => 'dibatalkan otomatis karena belum dibayar atau belum dikonfirmasi sampai batas waktu.',
        };
    }

    /** Teks status singkat untuk templat resmi WhatsApp ({{3}}). */
    public function AmbilLabel(): string
    {
        return match ($this) {
            self::PembayaranDiterima => 'Pembayaran diterima',
            self::Dikonfirmasi => 'Dikonfirmasi',
            self::SiapDiambil => 'Siap diambil',
            self::Dikirim => 'Sedang dikirim',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Kedaluwarsa => 'Dibatalkan otomatis',
        };
    }
}

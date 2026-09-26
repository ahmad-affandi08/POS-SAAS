<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Enum;

/**
 * Katalog penyedia gerbang pembayaran QRIS dinamis (F-08, P-05, PRD v2.06). Sejak v2.06 setiap tenant memakai akun
 * merchant miliknya sendiri (dana langsung ke rekening tenant); platform hanya mengatur penyedia mana yang boleh
 * dipilih. Nilai enum = kode adaptor `PembuatGerbangPembayaran` dan nilai lama `PenyediaIntegrasi` (P-05).
 *
 * Bidang pengaturan tidak rahasia dan boleh tampil; bidang kredensial disimpan terenkripsi dan tidak pernah ditampilkan
 * ulang (hanya 4 karakter terakhir, BR-P05.1). Mode Sandbox/Produksi tidak termasuk bidang: disimpan di kolom
 * `Lingkungan` dan diteruskan ke adaptor sebagai pengaturan `Mode`.
 */
enum PenyediaGerbang: string
{
    case Midtrans = 'Midtrans';
    case Xendit = 'Xendit';
    case Tripay = 'Tripay';
    case Duitku = 'Duitku';
    case Ipaymu = 'Ipaymu';
    case Doku = 'Doku';

    /** Segmen URL webhook (`/webhook/{kode}/{tokenWebhook}`). */
    public function AmbilKodeUrl(): string
    {
        return strtolower($this->value);
    }

    public static function DariKodeUrl(string $kode): ?self
    {
        foreach (self::cases() as $penyedia) {
            if ($penyedia->AmbilKodeUrl() === $kode) {
                return $penyedia;
            }
        }

        return null;
    }

    /** Xendit menentukan sandbox/produksi dari jenis kuncinya, sehingga tidak memakai pengaturan Mode. */
    public function CekPakaiMode(): bool
    {
        return $this !== self::Xendit;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Midtrans => 'Midtrans',
            self::Xendit => 'Xendit',
            self::Tripay => 'Tripay',
            self::Duitku => 'Duitku',
            self::Ipaymu => 'iPaymu',
            self::Doku => 'DOKU',
        };
    }

    public function AmbilKeterangan(): string
    {
        return match ($this) {
            self::Midtrans => 'QRIS dinamis lewat Core API. URL notifikasi dikirim otomatis per transaksi; salin juga URL webhook ke dasbor Midtrans sebagai cadangan.',
            self::Xendit => 'QRIS dinamis lewat QR Codes API. Salin URL webhook ke pengaturan callback QR di dasbor Xendit.',
            self::Tripay => 'QRIS dinamis lewat transaksi closed payment. URL callback dikirim otomatis per transaksi.',
            self::Duitku => 'QRIS dinamis lewat API v2. URL callback dikirim otomatis per transaksi.',
            self::Ipaymu => 'QRIS dinamis lewat direct payment. Notifikasi dikonfirmasi ulang ke iPaymu sebelum dipercaya.',
            self::Doku => 'DOKU Checkout (halaman bayar QRIS). Salin URL webhook ke pengaturan notifikasi di dasbor DOKU.',
        };
    }

    /**
     * @return list<array{Kunci: string, Label: string, Jenis: string, Wajib: bool, Opsi?: list<string>, Bawaan?: string|int, Keterangan?: string}>
     */
    public function AmbilBidangPengaturan(): array
    {
        return match ($this) {
            self::Midtrans => [
                ['Kunci' => 'Akuisitor', 'Label' => 'Akuisitor QRIS', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['gopay', 'airpay shopee'], 'Bawaan' => 'gopay'],
            ],
            self::Xendit => [],
            self::Tripay => [
                ['Kunci' => 'KodeMerchant', 'Label' => 'Kode merchant', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Misal T12345'],
                ['Kunci' => 'KanalQris', 'Label' => 'Kanal QRIS', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['QRIS', 'QRISC', 'QRIS2'], 'Bawaan' => 'QRIS', 'Keterangan' => 'Kode kanal QRIS yang aktif di akun Tripay.'],
            ],
            self::Duitku => [
                ['Kunci' => 'KodeMerchant', 'Label' => 'Kode merchant', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Misal D1234'],
                ['Kunci' => 'KanalQris', 'Label' => 'Kanal QRIS', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['SP', 'NQ', 'GQ', 'SQ'], 'Bawaan' => 'SP', 'Keterangan' => 'SP ShopeePay, NQ Nobu, GQ Gudang Voucher, SQ Nusapay.'],
            ],
            self::Ipaymu => [
                ['Kunci' => 'NomorVa', 'Label' => 'Nomor VA iPaymu', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Di menu Integrasi dasbor iPaymu.'],
            ],
            self::Doku => [
                ['Kunci' => 'IdKlien', 'Label' => 'Client ID', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'DOKU Checkout menampilkan halaman bayar QRIS (QR berisi tautan halaman bayar).'],
            ],
        };
    }

    /**
     * @return list<array{Kunci: string, Label: string, Wajib: bool}>
     */
    public function AmbilBidangKredensial(): array
    {
        return match ($this) {
            self::Midtrans => [['Kunci' => 'KunciServer', 'Label' => 'Server key', 'Wajib' => true]],
            self::Xendit => [
                ['Kunci' => 'KunciRahasia', 'Label' => 'Secret API key', 'Wajib' => true],
                ['Kunci' => 'TokenCallback', 'Label' => 'Token verifikasi callback', 'Wajib' => true],
            ],
            self::Tripay => [
                ['Kunci' => 'KunciApi', 'Label' => 'API key', 'Wajib' => true],
                ['Kunci' => 'KunciPrivat', 'Label' => 'Private key', 'Wajib' => true],
            ],
            self::Duitku, self::Ipaymu => [['Kunci' => 'KunciApi', 'Label' => 'API key', 'Wajib' => true]],
            self::Doku => [['Kunci' => 'KunciRahasia', 'Label' => 'Secret key', 'Wajib' => true]],
        };
    }
}

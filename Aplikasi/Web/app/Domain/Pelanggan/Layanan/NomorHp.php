<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

/**
 * Nomor HP pelanggan (F-16a): disimpan angka saja berawalan kode negara (`0812…` → `62812…`, `+62 812…` →
 * `62812…`, `812…` → `62812…`), panjang 10–15 digit. Ditampilkan `0812-3456-7890`; di POS & log disamarkan.
 */
final class NomorHp
{
    public const PANJANG_MINIMAL = 10;

    public const PANJANG_MAKSIMAL = 15;

    /** Hasil normalisasi, atau null bila bukan nomor HP yang sah. */
    public static function Normalisasi(?string $masukan): ?string
    {
        if ($masukan === null || preg_match('/^[0-9+() .-]+$/', trim($masukan)) !== 1) {
            return null;
        }

        $angka = (string) preg_replace('/\D+/', '', $masukan);

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '62'.$angka;
        }

        $panjang = strlen($angka);

        return $panjang < self::PANJANG_MINIMAL || $panjang > self::PANJANG_MAKSIMAL ? null : $angka;
    }

    /** `62812345678901` → `0812-3456-7890-1`; nomor luar negeri → `+{angka}`. */
    public static function Format(string $nomor): string
    {
        if (! str_starts_with($nomor, '62')) {
            return '+'.$nomor;
        }

        return implode('-', str_split('0'.substr($nomor, 2), 4));
    }

    /** `62812345678901` → `0812****8901` (tampilan POS & catatan audit). */
    public static function Samarkan(string $nomor): string
    {
        $lokal = str_starts_with($nomor, '62') ? '0'.substr($nomor, 2) : '+'.$nomor;

        return substr($lokal, 0, 4).str_repeat('*', max(0, strlen($lokal) - 8)).substr($lokal, -4);
    }
}

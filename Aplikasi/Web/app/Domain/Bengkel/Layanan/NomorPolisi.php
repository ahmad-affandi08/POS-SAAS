<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Layanan;

/**
 * Nomor polisi kendaraan (§9.10): huruf besar dengan satu spasi antarbagian. Pola Indonesia `AD1234XY` / `b 1234 abc`
 * dirapikan menjadi `AD 1234 XY` / `B 1234 ABC` supaya keunikan per tenant tidak bisa dilewati dengan beda spasi atau
 * huruf kecil. Pola lain (kendaraan dinas, plat sementara) cukup dirapikan spasi & hurufnya.
 */
final class NomorPolisi
{
    /** Null bila kosong, terlalu pendek/panjang, atau berisi karakter selain huruf, angka, spasi, dan tanda hubung. */
    public static function Normalisasi(?string $masukan): ?string
    {
        $teks = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $masukan)));

        if ($teks === '' || preg_match('/^[A-Z0-9 -]+$/', $teks) !== 1) {
            return null;
        }

        $rapat = str_replace([' ', '-'], '', $teks);

        if (preg_match('/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/', $rapat, $bagian) === 1) {
            $teks = trim("{$bagian[1]} {$bagian[2]} {$bagian[3]}");
        }

        $panjang = strlen($rapat);

        return $panjang < 3 || strlen($teks) > 20 ? null : $teks;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Enum\JenisNomorUrutKatalog;
use App\Domain\Katalog\Model\ProdukBarcode;

/**
 * Barcode internal EAN-13 per tenant (BR-03.1, DesainF03 C.2/H8): `config('katalog.Barcode.Awalan')` ("20") +
 * nomor urut 10 digit + digit periksa. Barcode yang sudah dipakai dilewati. Dipanggil di dalam transaksi Aksi.
 */
final class PembuatBarcode
{
    public function __construct(private readonly PenghitungNomorUrutKatalog $penghitung) {}

    public function Buat(): string
    {
        $awalan = (string) config('katalog.Barcode.Awalan', '20');
        $panjangNomor = 12 - strlen($awalan);

        do {
            $duaBelasDigit = $awalan.str_pad((string) $this->penghitung->AmbilBerikutnya(JenisNomorUrutKatalog::Barcode), $panjangNomor, '0', STR_PAD_LEFT);
            $barcode = $duaBelasDigit.self::HitungDigitPeriksa($duaBelasDigit);
        } while (ProdukBarcode::query()->where('Barcode', $barcode)->exists());

        return $barcode;
    }

    /** Digit periksa EAN-13 dari 12 digit: bobot 1,3 dari kiri; digit = (10 − jumlah mod 10) mod 10. */
    public static function HitungDigitPeriksa(string $duaBelasDigit): int
    {
        $jumlah = 0;

        foreach (str_split($duaBelasDigit) as $posisi => $digit) {
            $jumlah += (int) $digit * ($posisi % 2 === 0 ? 1 : 3);
        }

        return (10 - $jumlah % 10) % 10;
    }
}

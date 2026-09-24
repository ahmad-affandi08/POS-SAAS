<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;
use InvalidArgumentException;

/**
 * Satu pembayaran untuk mesin kalkulasi F-07a. Hanya pembedaan tunai/non-tunai yang memengaruhi hitungan
 * (pembulatan tunai BR-08.6 & kembalian). Tunai tanpa jumlah = uang pas; non-tunai wajib berjumlah.
 */
final readonly class DataPembayaranKalkulasi
{
    public function __construct(
        public bool $tunai,
        public ?Uang $jumlah = null,
    ) {
        if ($jumlah === null && ! $tunai) {
            throw new InvalidArgumentException('Jumlah pembayaran non-tunai wajib diisi.');
        }

        if ($jumlah !== null && $jumlah->BernilaiNegatif()) {
            throw new InvalidArgumentException("Jumlah pembayaran tidak boleh negatif: {$jumlah}");
        }
    }
}

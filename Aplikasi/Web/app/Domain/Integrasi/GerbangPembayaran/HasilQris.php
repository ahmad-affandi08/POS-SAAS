<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

use Carbon\CarbonImmutable;

/**
 * Tagihan QRIS yang dibuat gerbang. [isiQr] = string QRIS (EMVCo) untuk digambar di layar kasir/pelanggan; bila
 * penyedia hanya memberi halaman bayar (misal DOKU Checkout), [isiQr] berisi URL halaman itu dan [halamanBayar] true.
 */
final readonly class HasilQris
{
    public function __construct(
        public string $idReferensi,
        public string $isiQr,
        public ?CarbonImmutable $kedaluwarsaPada,
        public bool $halamanBayar = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;

/**
 * Aturan PIN kasir (F-02 langkah 4, §20.2): tepat 6 angka, bukan angka yang sama semua (111111), dan bukan deret
 * naik/turun (123456, 654321, 012345, ...). PIN disimpan sebagai hash `Hash::make`, tidak pernah ditampilkan.
 */
final class PenjagaPin
{
    public function PastikanKuat(string $pin, string $bidang = 'Pin'): void
    {
        if (preg_match('/^\d{6}$/', $pin) !== 1) {
            throw new PelanggaranAturanBisnis('PinTidakValid', 'PIN harus 6 angka.', $bidang);
        }

        if (count(array_unique(str_split($pin))) === 1) {
            throw new PelanggaranAturanBisnis('PinLemah', 'PIN terlalu mudah ditebak: jangan pakai angka yang sama semua, misal 111111.', $bidang);
        }

        if (str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            throw new PelanggaranAturanBisnis('PinLemah', 'PIN terlalu mudah ditebak: jangan pakai angka berurutan, misal 123456 atau 654321.', $bidang);
        }
    }
}

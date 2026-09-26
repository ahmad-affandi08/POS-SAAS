<?php

declare(strict_types=1);

namespace App\Domain\Integrasi;

/**
 * Hasil tes kredensial gerbang pembayaran atau pengirim pesan. Pesan tidak pernah memuat kredensial.
 */
final readonly class HasilUjiLayanan
{
    private function __construct(public bool $berhasil, public string $pesan) {}

    public static function Berhasil(string $pesan): self
    {
        return new self(true, $pesan);
    }

    public static function Gagal(string $pesan): self
    {
        return new self(false, $pesan);
    }
}

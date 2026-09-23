<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Data;

final readonly class HasilUjiKoneksi
{
    public function __construct(
        public bool $berhasil,
        public string $pesan,
    ) {}

    public static function Berhasil(string $pesan): self
    {
        return new self(true, $pesan);
    }

    public static function Gagal(string $pesan): self
    {
        return new self(false, $pesan);
    }
}

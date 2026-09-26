<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp;

final readonly class HasilKirimWhatsapp
{
    private function __construct(public bool $berhasil, public ?string $idPesan, public string $pesan) {}

    public static function Berhasil(?string $idPesan): self
    {
        return new self(true, $idPesan, 'Terkirim.');
    }

    public static function Gagal(string $pesan): self
    {
        return new self(false, null, mb_substr($pesan, 0, 300));
    }
}

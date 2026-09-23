<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Organisasi\Data\DataPemilikBaru;

final readonly class DataPendaftaran
{
    public function __construct(
        public DataPemilikBaru $pemilik,
        public string $namaUsaha,
        public ?string $kodePaket,
        public ?string $ip,
    ) {}
}

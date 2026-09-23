<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Data;

use App\Domain\Tenant\Enum\JenisDokumenLegal;

final readonly class DataDokumenLegal
{
    public function __construct(
        public JenisDokumenLegal $jenis,
        public string $judul,
        public string $isi,
        public ?string $ringkasanPerubahan,
        public bool $materiil,
        public string $berlakuMulai,
    ) {}
}

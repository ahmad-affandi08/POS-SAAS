<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Data;

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;

final readonly class DataKonfigurasiIntegrasi
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial  Hanya kolom yang diisi; kolom kosong = pertahankan nilai lama.
     */
    public function __construct(
        public JenisIntegrasi $jenis,
        public LingkunganIntegrasi $lingkungan,
        public array $pengaturan,
        public array $kredensial,
        public int $rotasiSetiapHari,
        public ?string $alasan,
    ) {}
}

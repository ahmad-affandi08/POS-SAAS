<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Data;

use App\Domain\Integrasi\Enum\LingkunganGerbang;
use App\Domain\Integrasi\Enum\PenyediaGerbang;

final readonly class DataGerbangPembayaranTenant
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial  Hanya bidang yang diisi; bidang kosong = pertahankan nilai lama.
     */
    public function __construct(
        public PenyediaGerbang $penyedia,
        public LingkunganGerbang $lingkungan,
        public array $pengaturan,
        public array $kredensial,
    ) {}
}

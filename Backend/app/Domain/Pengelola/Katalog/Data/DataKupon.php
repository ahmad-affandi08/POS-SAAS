<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Data;

use App\Domain\Tenant\Enum\JenisKupon;
use Carbon\CarbonImmutable;

final readonly class DataKupon
{
    /**
     * @param  list<string>|null  $daftarKodePaket  null = semua paket
     */
    public function __construct(
        public string $kode,
        public JenisKupon $jenis,
        public string $nilai,
        public int $durasiBulan,
        public ?int $kuota,
        public ?array $daftarKodePaket,
        public ?CarbonImmutable $berlakuSampai,
        public bool $aktif,
    ) {}
}

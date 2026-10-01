<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Data;

use App\Domain\Tenant\Enum\JenisPengumuman;
use Carbon\CarbonImmutable;

/** Isian draf pengumuman platform P-10 (PGL-19). Waktu dalam UTC. */
final readonly class DataPengumumanPlatform
{
    /**
     * @param  array{KodePaket: list<string>, Sektor: list<string>, Platform: list<string>, VersiMinimal: string|null, VersiMaksimal: string|null}  $sasaran
     */
    public function __construct(
        public string $judul,
        public string $isi,
        public JenisPengumuman $jenis,
        public array $sasaran,
        public ?string $tautan,
        public CarbonImmutable $tampilMulai,
        public CarbonImmutable $tampilSampai,
        public ?CarbonImmutable $pemeliharaanMulai,
        public ?CarbonImmutable $pemeliharaanSelesai,
    ) {}
}

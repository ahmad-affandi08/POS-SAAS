<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Data;

use App\Domain\Tenant\Enum\StatusPaket;

final readonly class DataAddon
{
    /**
     * @param  array<string, int>  $tambahanBatas
     */
    public function __construct(
        public string $kode,
        public string $nama,
        public string $hargaBulanan,
        public ?string $kunciFitur,
        public array $tambahanBatas,
        public StatusPaket $status,
    ) {}
}

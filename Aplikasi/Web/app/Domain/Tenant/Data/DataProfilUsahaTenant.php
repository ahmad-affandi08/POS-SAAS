<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

/**
 * Profil usaha tenant (F-01 langkah 1). `zonaWaktu` = zona IANA dari kota outlet wizard; null = tidak diubah.
 */
final readonly class DataProfilUsahaTenant
{
    public function __construct(
        public string $nama,
        public ?string $npwp,
        public bool $pkp,
        public ?string $zonaWaktu,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Data;

/**
 * Isian langkah 1 panduan awal: profil usaha (tenant) + alamat & kota outlet wizard.
 */
final readonly class DataProfilUsaha
{
    public function __construct(
        public string $namaUsaha,
        public ?string $alamat,
        public string $kodeKota,
        public ?string $npwp,
        public bool $pkp,
    ) {}
}

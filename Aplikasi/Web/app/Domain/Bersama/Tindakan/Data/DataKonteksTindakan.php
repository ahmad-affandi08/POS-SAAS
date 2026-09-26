<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Data;

use Carbon\CarbonImmutable;

/**
 * Siapa yang membuka Kotak Tindakan (D-23 C): tenant, pengguna, izinnya (kunci string, pemilik = semua), outlet yang
 * boleh dilihat (null = semua), dan tanggal bisnis hari ini.
 */
final readonly class DataKonteksTindakan
{
    /**
     * @param  list<string>  $izin
     * @param  list<int>|null  $idOutletBoleh
     */
    public function __construct(
        public int $idTenant,
        public int $idPengguna,
        public bool $pemilik,
        public array $izin,
        public ?array $idOutletBoleh,
        public CarbonImmutable $hariIni,
    ) {}

    public function CekIzin(string $kunci): bool
    {
        return $this->pemilik || in_array($kunci, $this->izin, true);
    }
}

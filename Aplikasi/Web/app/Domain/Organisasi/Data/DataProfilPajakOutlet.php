<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Pengaturan pajak outlet dari panduan awal (F-01 langkah 3), digabung ke `Outlet.ProfilPajak`. `persenBiayaLayanan`
 * = string desimal persen service charge (0–10, §12.1); tidak pernah float.
 */
final readonly class DataProfilPajakOutlet
{
    public const PERSEN_BIAYA_LAYANAN_MAKSIMAL = '10';

    public function __construct(
        public bool $pkp,
        public bool $pungutPbjt,
        public bool $biayaLayananAktif,
        public string $persenBiayaLayanan,
        public bool $hargaTermasukPajak,
    ) {}
}

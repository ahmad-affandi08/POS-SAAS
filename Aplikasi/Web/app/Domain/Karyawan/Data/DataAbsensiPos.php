<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Data;

use Carbon\CarbonImmutable;

/**
 * Item outbox absensi dari POS (F-18): `Absensi.Masuk` (Uuid item = Uuid absensi) atau `Absensi.Keluar`
 * ([uuidAbsensi] = absensi masuknya). [swafoto] JPEG base64; null = perangkat tanpa kamera.
 */
final readonly class DataAbsensiPos
{
    public function __construct(
        public string $uuid,
        public int $idTenant,
        public int $idOutlet,
        public ?int $idPerangkat,
        public string $uuidPengguna,
        public CarbonImmutable $waktu,
        public ?string $swafoto,
        public ?string $uuidAbsensi = null,
    ) {}
}

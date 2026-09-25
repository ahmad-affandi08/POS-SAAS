<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Peristiwa;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Retur penjualan diterima server (F-09). `tanggalBisnis` & `idOutlet` = milik retur (retur mengurangi pada tanggal returnya).
 * Dikirim setelah commit; penangan non-kritis (ringkasan laporan F-14a) berjalan di antrean (aturan #10).
 */
final class ReturPenjualanDiterima implements PeristiwaDokumenPenjualan, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idOutlet,
        public readonly string $tanggalBisnis,
        public readonly int $idDokumen,
    ) {}

    public function AmbilIdTenant(): int
    {
        return $this->idTenant;
    }

    public function AmbilIdOutlet(): int
    {
        return $this->idOutlet;
    }

    public function AmbilTanggalBisnis(): string
    {
        return $this->tanggalBisnis;
    }
}

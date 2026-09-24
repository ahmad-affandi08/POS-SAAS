<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Tugas;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

/**
 * Validasi impor stok awal besar di antrean per potongan (DesainF05a C.7). Membawa IdTenant.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim E (DesainF05a G).
 */
final class ValidasiImporStokAwalTugas implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idPengguna,
        public readonly int $idImporStokAwal,
    ) {}

    public function handle(): void
    {
        throw new LogicException('F-05a Tim E');
    }
}

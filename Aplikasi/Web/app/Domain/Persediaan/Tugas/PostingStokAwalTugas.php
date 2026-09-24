<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Tugas;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

/**
 * Posting stok awal besar di antrean (DesainF05a C.6.3). Membawa IdTenant.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G).
 */
final class PostingStokAwalTugas implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idPengguna,
        public readonly int $idStokAwal,
    ) {}

    public function handle(): void
    {
        throw new LogicException('F-05a Tim C');
    }
}

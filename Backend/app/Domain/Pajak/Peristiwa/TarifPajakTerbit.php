<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Peristiwa;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tarif pajak master terbit (P-02 langkah 4–5). Penangan notifikasi tenant terdampak (F-02) dan delta sinkron POS
 * (§18) ditambahkan bersama flow tersebut.
 */
final readonly class TarifPajakTerbit
{
    use Dispatchable;

    public function __construct(public int $idTarifPajak) {}
}

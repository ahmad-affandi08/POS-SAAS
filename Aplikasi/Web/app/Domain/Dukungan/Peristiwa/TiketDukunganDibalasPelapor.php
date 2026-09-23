<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Peristiwa;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pengguna tenant membalas tiket (P-09). Penangan memberi tahu penanggung jawab tiket, bila ada.
 */
final class TiketDukunganDibalasPelapor implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idTiket,
        public readonly string $uuid,
        public readonly string $nomor,
        public readonly string $judul,
        public readonly ?int $idPenanggungJawab,
        public readonly bool $dibukaLagi,
    ) {}
}

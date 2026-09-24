<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

/**
 * Hasil `PostingJurnal`/`BalikkanJurnal` (DesainF05a C.5). `sudahAda` = jurnal sumber ini sudah pernah diposting
 * (pemutaran ulang idempoten).
 */
final readonly class HasilPostingJurnal
{
    public function __construct(
        public int $idJurnal,
        public string $uuid,
        public string $nomor,
        public bool $sudahAda,
    ) {}
}

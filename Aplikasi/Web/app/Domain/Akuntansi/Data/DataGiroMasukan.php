<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use Carbon\CarbonImmutable;

/** v3.42: isian bilyet giro/cek mundur pada pelunasan piutang atau pembayaran hutang. */
final readonly class DataGiroMasukan
{
    public function __construct(
        public string $nomorGiro,
        public string $namaBank,
        public CarbonImmutable $tanggalJatuhTempo,
    ) {}
}

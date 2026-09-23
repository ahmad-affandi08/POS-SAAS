<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;

/**
 * Satu akun COA dari isi template sektor (F-01, BR-01.2).
 */
final readonly class DataAkunTemplate
{
    public function __construct(
        public string $kode,
        public string $nama,
        public TipeAkun $tipe,
        public SaldoNormal $saldoNormal,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Enum\JenisMutasiKas;
use Carbon\CarbonImmutable;

/**
 * Masukan `CatatMutasiKas` (F-06 langkah 4) dari item outbox `MutasiKas.Catat`. `uuidPenyetuju` = supervisor yang
 * memasukkan PIN di perangkat untuk kas keluar di atas batas (BR-06.4). `bukti` = foto bukti JPEG base64 (K-18, opsional).
 */
final readonly class DataMutasiKas
{
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public string $uuidShift,
        public JenisMutasiKas $jenis,
        public ?string $uuidKategori,
        public Uang $jumlah,
        public ?string $catatan,
        public string $uuidPencatat,
        public CarbonImmutable $dicatatPada,
        public ?string $uuidPenyetuju,
        public ?string $bukti = null,
    ) {}
}

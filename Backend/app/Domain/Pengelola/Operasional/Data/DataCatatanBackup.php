<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Data;

use App\Domain\Pengelola\Operasional\Enum\HasilBackup;
use App\Domain\Pengelola\Operasional\Enum\JenisCatatanBackup;
use Illuminate\Support\Carbon;

final readonly class DataCatatanBackup
{
    public function __construct(
        public JenisCatatanBackup $jenis,
        public HasilBackup $hasil,
        public Carbon $selesaiPada,
        public ?int $ukuranByte = null,
        public ?string $lokasi = null,
        public ?string $keterangan = null,
    ) {}
}

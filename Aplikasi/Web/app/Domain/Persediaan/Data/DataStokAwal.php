<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Persediaan\Enum\SumberStokAwal;
use Carbon\CarbonImmutable;

/**
 * Masukan `SimpanStokAwal` (DesainF05a C.6). `uuid` = Uuid klien (buat idempoten); `versiDiubahPada` = `DiubahPada`
 * yang dilihat pengguna saat mengubah draf (ISO), untuk menolak `DokumenBerubah`. `idImpor` terisi bila dibuat impor.
 */
final readonly class DataStokAwal
{
    /**
     * @param  list<DataBarisStokAwal>  $baris
     */
    public function __construct(
        public ?string $uuid,
        public int $idGudang,
        public CarbonImmutable $tanggal,
        public ?string $catatan,
        public array $baris,
        public SumberStokAwal $sumber = SumberStokAwal::Manual,
        public ?int $idImpor = null,
        public ?string $versiDiubahPada = null,
    ) {}
}

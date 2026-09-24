<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use Carbon\CarbonImmutable;

/**
 * Masukan `CatatMutasiStok` (DesainF05a C.2): satu dokumen sumber beserta baris mutasinya. Diproses dalam urutan
 * baris; idempoten per (jenisReferensi, idReferensi, kunciBaris).
 */
final readonly class DataDokumenMutasi
{
    /**
     * @param  list<DataBarisMutasi>  $baris
     */
    public function __construct(
        public JenisReferensiMutasi $jenisReferensi,
        public int $idReferensi,
        public ?string $uuidReferensi,
        public ?string $nomorReferensi,
        public CarbonImmutable $tanggalBisnis,
        public ?int $idPengguna,
        public ?int $idPerangkat,
        public array $baris,
    ) {}
}

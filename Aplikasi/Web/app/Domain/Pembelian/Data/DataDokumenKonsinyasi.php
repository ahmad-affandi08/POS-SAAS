<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Pembelian\Enum\JenisDokumenKonsinyasi;
use Carbon\CarbonImmutable;

/** Masukan `CatatDokumenKonsinyasi` (F-05i). Lokasi stok sudah diperiksa aksesnya oleh kontroler. */
final readonly class DataDokumenKonsinyasi
{
    /**
     * @param  list<DataBarisKonsinyasi>  $baris
     */
    public function __construct(
        public JenisDokumenKonsinyasi $jenis,
        public string $uuidPemasok,
        public DataInfoGudang $gudang,
        public CarbonImmutable $tanggal,
        public array $baris,
        public ?string $catatan,
        public int $idPengguna,
    ) {}
}

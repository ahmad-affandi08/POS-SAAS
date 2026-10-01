<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Enum\KelompokAsetTetap;
use App\Domain\Akuntansi\Enum\SumberDanaAsetTetap;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/** Masukan `CatatAsetTetap` (FIN-10). `uuidAkunKasBank` wajib bila sumber dana `KasBank`. */
final readonly class DataAsetTetap
{
    public function __construct(
        public string $nama,
        public KelompokAsetTetap $kelompok,
        public ?int $idOutlet,
        public CarbonImmutable $tanggalPerolehan,
        public Uang $hargaPerolehan,
        public Uang $nilaiSisa,
        public int $umurBulan,
        public SumberDanaAsetTetap $sumberDana,
        public ?string $uuidAkunKasBank,
        public Uang $akumulasiAwal,
        public ?string $catatan,
        public int $idPengguna,
    ) {}
}

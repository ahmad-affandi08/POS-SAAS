<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/** Isian pelunasan piutang (F-12): akun kas/bank, tanggal, dan alokasi per Uuid piutang (boleh sebagian). */
final readonly class DataPembayaranPiutang
{
    /**
     * @param  array<string, Uang>  $alokasi  kunci = Uuid piutang
     */
    public function __construct(
        public string $uuidPelanggan,
        public string $uuidAkun,
        public CarbonImmutable $tanggal,
        public array $alokasi,
        public ?string $catatan,
        public int $idPengguna,
    ) {}
}

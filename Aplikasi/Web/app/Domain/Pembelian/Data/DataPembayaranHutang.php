<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Masukan `SimpanPembayaranHutang` (F-04 fase 1): akun kas/bank (Uuid), alokasi Uuid faktur → jumlah (> 0, ≤ sisa).
 */
final readonly class DataPembayaranHutang
{
    /**
     * @param  array<string, Uang>  $alokasi  kunci = Uuid faktur
     */
    public function __construct(
        public string $uuidPemasok,
        public string $uuidAkun,
        public CarbonImmutable $tanggal,
        public array $alokasi,
        public ?string $catatan,
        public ?UploadedFile $lampiran,
        public int $idPengguna,
    ) {}
}

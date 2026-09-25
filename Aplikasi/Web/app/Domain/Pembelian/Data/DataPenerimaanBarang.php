<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Masukan `TerimaBarang` (F-04 fase 1). `uuidPesananPembelian` terisi = GRN dari PO (pemasok & lokasi dari PO);
 * selain itu `uuidPemasok` opsional dan `idGudang` wajib (sudah diperiksa batas outlet oleh kontroler).
 */
final readonly class DataPenerimaanBarang
{
    /**
     * @param  list<DataBarisPenerimaanBarang>  $baris
     */
    public function __construct(
        public ?string $uuidPesananPembelian,
        public ?string $uuidPemasok,
        public ?int $idGudang,
        public CarbonImmutable $tanggal,
        public ?string $nomorSuratJalan,
        public Uang $ongkir,
        public ?string $catatan,
        public array $baris,
        public ?UploadedFile $lampiran,
        public int $idPengguna,
    ) {}
}

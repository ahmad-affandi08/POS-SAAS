<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Masukan `SimpanFakturPembelian` (F-04 fase 1): GRN (Uuid) satu pemasok yang belum difakturkan, baris per baris GRN
 * (boleh kosong = harga GRN), ongkir faktur (null = ongkir GRN), jatuh tempo (null = tanggal + termin).
 */
final readonly class DataFakturPembelian
{
    /**
     * @param  list<string>  $uuidPenerimaan
     * @param  list<DataBarisFakturPembelian>  $baris
     */
    public function __construct(
        public string $uuidPemasok,
        public string $nomorFakturPemasok,
        public CarbonImmutable $tanggal,
        public ?CarbonImmutable $jatuhTempo,
        public array $uuidPenerimaan,
        public array $baris,
        public ?Uang $ongkir,
        public ?string $catatan,
        public ?UploadedFile $lampiran,
        public int $idPengguna,
    ) {}
}

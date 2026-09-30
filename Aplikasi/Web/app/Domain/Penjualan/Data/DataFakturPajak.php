<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Satu Faktur Pajak Keluaran Coretax yang siap diekspor (PRD v3.12): asalnya satu `FakturPenjualan` grosir.
 * `selisihDpp`/`selisihPpn` = angka Coretax dikurangi angka faktur internal (pembulatan per baris vs per dokumen);
 * dilaporkan, tidak pernah disesuaikan diam-diam.
 */
final readonly class DataFakturPajak
{
    /**
     * @param  list<DataBarisFakturPajak>  $baris
     */
    public function __construct(
        public string $nomorFaktur,
        public string $tanggal,
        public string $namaPembeli,
        public string $alamatPembeli,
        public ?string $emailPembeli,
        /** `TIN` (NPWP) atau `National ID` (NIK). */
        public string $jenisDokumenPembeli,
        public string $tinPembeli,
        public string $nomorDokumenPembeli,
        public string $idTkuPembeli,
        public array $baris,
        public string $totalDpp,
        public string $totalPpn,
        public string $selisihDpp,
        public string $selisihPpn,
    ) {}
}

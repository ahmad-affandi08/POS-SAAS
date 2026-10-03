<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Satu nota retur pajak dari retur grosir (PRD v4.08): barang yang kembali atas penyerahan yang sudah dibuatkan
 * Faktur Pajak. Baris dihitung dengan cara yang sama dengan baris Faktur Pajak (`PenghitungBarisFakturPajak`);
 * `selisihDpp`/`selisihPpn` = angka per baris dikurangi angka dokumen retur (pembulatan), dilaporkan apa adanya.
 */
final readonly class DataNotaReturPajak
{
    /**
     * @param  list<DataBarisFakturPajak>  $baris
     */
    public function __construct(
        public string $nomorRetur,
        public string $tanggalRetur,
        public string $nomorFaktur,
        public string $tanggalFaktur,
        public string $nomorFakturPajak,
        public string $namaPembeli,
        /** `TIN` (NPWP) atau `National ID` (NIK). */
        public string $jenisDokumenPembeli,
        public string $tinPembeli,
        public string $nomorDokumenPembeli,
        public string $alasan,
        public array $baris,
        public string $totalDpp,
        public string $totalPpn,
        public string $selisihDpp,
        public string $selisihPpn,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Isian unggah bukti transfer tagihan langganan (P-08 langkah 3, transfer manual). Berkas sudah lolos validasi
 * MIME & ukuran di lapisan Permintaan; aksi tetap menyimpannya hanya di disk privat.
 */
final readonly class DataBuktiTransfer
{
    public function __construct(
        public UploadedFile $berkas,
        public string $jumlah,
        public CarbonImmutable $tanggalTransfer,
        public string $bankPengirim,
        public string $namaPengirim,
        public string $kodeRekeningTujuan,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use InvalidArgumentException;

/**
 * Satu baris penjualan untuk mesin kalkulasi F-07a.
 *
 * - `hargaPilihan`: tambahan harga modifier/varian per satuan (bawaan 0).
 * - `hargaTermasukPajak`: null = ikut pengaturan dokumen.
 * - `kodePajak`: null = semua pajak dokumen berlaku; `[]` = baris tanpa pajak.
 * - `potongan`: diskon manual baris dan promo item yang sudah diterapkan (persen dihitung dari Bruto baris).
 */
final readonly class DataBarisKalkulasi
{
    /**
     * @param  list<string>|null  $kodePajak
     * @param  list<DataPotongan>  $potongan
     */
    public function __construct(
        public Kuantitas $jumlah,
        public Uang $hargaSatuan,
        public ?Uang $hargaPilihan = null,
        public ?bool $hargaTermasukPajak = null,
        public ?array $kodePajak = null,
        public array $potongan = [],
    ) {
        if ($jumlah->Bandingkan(Kuantitas::Nol()) <= 0) {
            throw new InvalidArgumentException("Jumlah baris harus lebih dari 0: {$jumlah}");
        }

        if ($hargaSatuan->BernilaiNegatif()) {
            throw new InvalidArgumentException("Harga satuan tidak boleh negatif: {$hargaSatuan}");
        }

        if ($hargaPilihan !== null && $hargaPilihan->BernilaiNegatif()) {
            throw new InvalidArgumentException("Harga pilihan tidak boleh negatif: {$hargaPilihan}");
        }
    }
}

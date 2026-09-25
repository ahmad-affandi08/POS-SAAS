<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Keluaran mesin kalkulasi F-07a. `kembalian` null bila tidak ada pembayaran tunai. `pajak` berurutan sesuai pajak
 * dokumen dan diberi kunci kode pajak. `diskonPoin` (F-16b) adalah bagian `diskonPesanan` dari penukaran poin.
 */
final readonly class HasilKalkulasi
{
    /**
     * @param  array<string, HasilPajakKalkulasi>  $pajak
     * @param  list<HasilBarisKalkulasi>  $baris
     */
    public function __construct(
        public Uang $subtotal,
        public Uang $diskonBaris,
        public Uang $diskonPesanan,
        public Uang $totalDiskon,
        public Uang $biayaLayanan,
        public Uang $totalPajak,
        public Uang $totalPajakEksklusif,
        public Uang $pembulatan,
        public Uang $totalAkhir,
        public ?Uang $kembalian,
        public array $pajak,
        public array $baris,
        public Uang $diskonPoin,
    ) {}

    /**
     * Bentuk larik berkunci PascalCase (sama dengan `Harapan` test vector dan key JSON API).
     *
     * @return array<string, mixed>
     */
    public function KeLarik(): array
    {
        return [
            'Subtotal' => $this->subtotal->KeString(),
            'DiskonBaris' => $this->diskonBaris->KeString(),
            'DiskonPesanan' => $this->diskonPesanan->KeString(),
            'DiskonPoin' => $this->diskonPoin->KeString(),
            'TotalDiskon' => $this->totalDiskon->KeString(),
            'BiayaLayanan' => $this->biayaLayanan->KeString(),
            'TotalPajak' => $this->totalPajak->KeString(),
            'TotalPajakEksklusif' => $this->totalPajakEksklusif->KeString(),
            'Pembulatan' => $this->pembulatan->KeString(),
            'TotalAkhir' => $this->totalAkhir->KeString(),
            'Kembalian' => $this->kembalian?->KeString(),
            'Pajak' => array_map(fn (HasilPajakKalkulasi $pajak): array => $pajak->KeLarik(), $this->pajak),
            'Baris' => array_map(fn (HasilBarisKalkulasi $baris): array => $baris->KeLarik(), $this->baris),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Data;

/**
 * Hasil `PenyelarasBarisHarga::Selaraskan`: jumlah baris berubah dan ringkasan harga lama/baru per Uuid satuan produk
 * yang berubah (untuk `LogAudit`).
 */
final readonly class HasilPenyelarasanHarga
{
    /**
     * @param  array<string, list<array{JumlahMinimum: string, Harga: string}>>  $nilaiLama
     * @param  array<string, list<array{JumlahMinimum: string, Harga: string}>>  $nilaiBaru
     */
    public function __construct(
        public HasilSimpanHarga $hasil,
        public array $nilaiLama,
        public array $nilaiBaru,
    ) {}
}

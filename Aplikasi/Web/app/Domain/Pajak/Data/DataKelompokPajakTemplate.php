<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Data;

/**
 * Satu kelompok pajak dari isi template sektor (F-01).
 */
final readonly class DataKelompokPajakTemplate
{
    /**
     * @param  list<array{KodeJenisPajak: string, DasarPengenaan: string, Urutan: int}>  $detail
     */
    public function __construct(
        public string $nama,
        public array $detail,
    ) {}
}

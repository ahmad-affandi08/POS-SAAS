<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Data;

use Carbon\CarbonInterface;

/**
 * Kiriman satu ronde dokumen ke dapur (F-10b): pesanan terbuka (`idPesananTerbuka`) atau penjualan mode cepat
 * (`idPenjualan`). Nomor dokumen, nama meja, dan label disalin ke tiket.
 */
final readonly class DataKirimDapur
{
    /**
     * @param  list<DataBarisKirimDapur>  $baris
     */
    public function __construct(
        public int $idOutlet,
        public ?int $idPesananTerbuka,
        public ?int $idPenjualan,
        public string $nomorDokumen,
        public ?string $namaMeja,
        public ?string $label,
        public int $ronde,
        public CarbonInterface $dikirimPada,
        public array $baris,
    ) {}
}

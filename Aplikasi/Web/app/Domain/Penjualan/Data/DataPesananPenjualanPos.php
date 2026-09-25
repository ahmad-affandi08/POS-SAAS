<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Item outbox `PesananPenjualan.Buat` (F-12 bagian 2) yang sudah divalidasi: pre-order dengan uang muka yang dibuat di
 * perangkat (bisa offline). `uuid` = Uuid pesanan dari perangkat; `pembayaran` = pembayaran uang muka (tanpa kembalian).
 */
final readonly class DataPesananPenjualanPos
{
    /**
     * @param  list<DataBarisPesananTerbuka>  $baris
     * @param  list<DataPembayaranPenjualanPos>  $pembayaran
     */
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public int $idOutlet,
        public string $uuidShift,
        public string $uuidPengguna,
        public string $uuidPelanggan,
        public string $nomor,
        public CarbonImmutable $dipesanPada,
        public CarbonImmutable $tanggalAmbil,
        public ?string $catatan,
        public Uang $totalPesanan,
        public array $baris,
        public array $pembayaran,
    ) {}
}

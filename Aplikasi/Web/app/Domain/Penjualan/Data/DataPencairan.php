<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan pencairan dana non-tunai (F-08, BR-08.4): setoran mana yang masuk rekening, untuk metode & outlet apa, dan
 * pembayaran penjualan mana yang dilunasinya.
 *
 * `jumlahBersih` adalah angka di mutasi rekening. Potongan platformnya **tidak** dikirim klien: ia selalu selisih
 * antara Σ pembayaran terpilih dan angka ini, sehingga tidak mungkin ada dokumen yang jumlahnya tidak menjelaskan
 * dirinya sendiri.
 */
final readonly class DataPencairan
{
    /**
     * @param  list<string>  $uuidPembayaran  `PenjualanPembayaran.Uuid` yang dicairkan
     */
    public function __construct(
        public string $uuidMetodePembayaran,
        public string $uuidOutlet,
        public string $uuidAkunTujuan,
        public CarbonImmutable $tanggal,
        public Uang $jumlahBersih,
        public array $uuidPembayaran,
        public ?string $referensi = null,
        public ?string $catatan = null,
    ) {}
}

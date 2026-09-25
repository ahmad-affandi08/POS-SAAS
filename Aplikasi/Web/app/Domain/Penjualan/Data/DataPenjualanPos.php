<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Masukan `TerimaPenjualanPos` (F-07b) dari item outbox `Penjualan.Buat`: penjualan lunas yang dibuat di perangkat
 * (bisa offline). `uuid` = `UuidKlien` (ULID perangkat). Semua harga, pajak, dan pengaturan adalah snapshot saat
 * transaksi (BR-07.2); server menghitung ulang dengan `MesinKalkulasi`.
 */
final readonly class DataPenjualanPos
{
    /**
     * @param  list<DataPajakPenjualanPos>  $pajak
     * @param  list<DataBarisPenjualanPos>  $baris
     * @param  list<DataPembayaranPenjualanPos>  $pembayaran
     */
    public function __construct(
        public string $uuid,
        public int $idPerangkat,
        public string $uuidShift,
        public string $uuidPengguna,
        public string $nomor,
        public KanalPenjualan $kanal,
        public CarbonImmutable $dibuatPada,
        public bool $hargaTermasukPajak,
        public BigDecimal $persenBiayaLayanan,
        public ?DataPembulatanTunai $pembulatanTunai,
        public array $pajak,
        public array $baris,
        public ?DataDiskonManual $diskonManualPesanan,
        public ?string $uuidPenyetujuDiskon,
        public array $pembayaran,
        public DataRingkasanPenjualanPos $ringkasan,
        public ?string $catatan,
        public ?string $uuidPesananTerbuka = null,
        public bool $kirimDapur = false,
    ) {}
}

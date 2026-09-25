<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Masukan `TerimaPenjualanPos` (F-07b) dari item outbox `Penjualan.Buat`: penjualan lunas yang dibuat di perangkat
 * (bisa offline). `uuid` = `UuidKlien` (ULID perangkat). Semua harga, pajak, dan pengaturan adalah snapshot saat
 * transaksi (BR-07.2); server menghitung ulang dengan `MesinKalkulasi`. `poinDitukar`/`nilaiTukarPoin` (F-16b): poin
 * pelanggan yang ditukar sebagai diskon pesanan sebelum pajak. `promo` (F-16c): promo yang diterapkan perangkat.
 * `uuidPenyetujuTempo` (F-12, BR-12.1): pemberi PIN untuk tempo di atas limit / piutang lewat jatuh tempo.
 * `kodeVoucher` (F-16c bagian 2): voucher yang dipesan online kasir untuk penjualan ini.
 */
final readonly class DataPenjualanPos
{
    /**
     * @param  list<DataPajakPenjualanPos>  $pajak
     * @param  list<DataBarisPenjualanPos>  $baris
     * @param  list<DataPembayaranPenjualanPos>  $pembayaran
     * @param  list<DataPromoPenjualanPos>  $promo
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
        public ?string $uuidPelanggan = null,
        public int $poinDitukar = 0,
        public ?Uang $nilaiTukarPoin = null,
        public array $promo = [],
        public ?string $uuidPenyetujuTempo = null,
        public ?string $kodeVoucher = null,
    ) {}
}

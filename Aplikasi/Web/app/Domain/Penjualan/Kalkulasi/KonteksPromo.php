<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/**
 * Konteks transaksi untuk promo (F-16c): waktu UTC, jam dinding lokal outlet (tanpa zona; hari & jam promo), outlet,
 * kanal, tier pelanggan, dan (F-16c bagian 2) Uuid promo yang vouchernya sudah tervalidasi untuk transaksi ini.
 * F-16c bagian 3: Uuid metode pembayaran semua pembayaran (null = belum memilih pembayaran), ada tidaknya pelanggan,
 * tanggal lahirnya (`YYYY-MM-DD`), jumlah transaksinya sebelum ini (null = tidak diketahui), dan pemakaian promo oleh
 * pelanggan itu (`{UuidPromo: {Hari, Promo}}`, tidak termasuk transaksi ini).
 */
final readonly class KonteksPromo
{
    public function __construct(
        public CarbonImmutable $waktu,
        public CarbonImmutable $waktuLokal,
        public ?string $uuidOutlet = null,
        public ?KanalPenjualan $kanal = null,
        public ?string $tier = null,
        /** @var list<string> */
        public array $voucher = [],
        /** @var list<string>|null */
        public ?array $metodeBayar = null,
        public bool $berpelanggan = false,
        public ?string $tanggalLahir = null,
        public ?int $jumlahTransaksiPelanggan = null,
        /** @var array<string, array{Hari: int, Promo: int}> */
        public array $pemakaianPelanggan = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Promo\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisAksiPromo;
use App\Domain\Penjualan\Enum\JenisKondisiPromo;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Isian promo dari back-office (F-16c). Waktu `mulaiPada`/`selesaiPada` sudah UTC; `jamMulai`/`jamSelesai` "HH:MM"
 * jam lokal outlet; daftar kosong = tanpa batasan. `wajibVoucher` (F-16c bagian 2): promo hanya berlaku dengan kode voucher.
 */
final readonly class DataPromo
{
    /**
     * @param  list<int>  $hari
     * @param  list<string>  $uuidOutlet
     * @param  list<KanalPenjualan>  $kanal
     * @param  list<string>  $tier
     * @param  list<string>  $uuidKondisi
     */
    public function __construct(
        public string $kode,
        public string $nama,
        public int $prioritas,
        public bool $eksklusif,
        public ?CarbonImmutable $mulaiPada,
        public ?CarbonImmutable $selesaiPada,
        public ?int $kuota,
        public array $hari,
        public ?string $jamMulai,
        public ?string $jamSelesai,
        public array $uuidOutlet,
        public array $kanal,
        public array $tier,
        public Uang $minimalSubtotal,
        public JenisKondisiPromo $kondisi,
        public array $uuidKondisi,
        public Kuantitas $jumlahMinimal,
        public JenisAksiPromo $aksi,
        public ?BigDecimal $persen,
        public ?Uang $jumlah,
        public ?Uang $harga,
        public ?int $beli,
        public ?int $gratis,
        public ?BigDecimal $persenGratis,
        public ?int $batasPerTransaksi,
        public int $idPengguna,
        public bool $wajibVoucher = false,
    ) {}
}

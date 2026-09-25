<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use Brick\Math\BigDecimal;

/**
 * Pengaturan loyalti efektif tenant (F-16b). `berlaku` = diaktifkan tenant **dan** paketnya punya fitur
 * `pelanggan.loyalti`. `nilaiTukarPoin` = Rupiah per poin saat ditukar sebagai diskon (bagian 2).
 */
final readonly class DataPengaturanLoyalti
{
    public function __construct(
        public bool $aktif,
        public bool $fiturAktif,
        public BigDecimal $belanjaPerPoin,
        public int $masaBerlakuBulan,
        public int $bulanEvaluasiTier,
        public BigDecimal $nilaiTukarPoin,
        public int $minimalTukarPoin,
    ) {}

    public function CekBerlaku(): bool
    {
        return $this->aktif && $this->fiturAktif;
    }
}

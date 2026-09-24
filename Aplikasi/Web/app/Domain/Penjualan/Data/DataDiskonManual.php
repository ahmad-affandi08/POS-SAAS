<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Kalkulasi\DataPotongan;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Diskon manual kasir (BR-07.3) pada baris atau pesanan: tepat satu dari persen (0–100) atau nominal Rupiah.
 */
final readonly class DataDiskonManual
{
    public function __construct(
        public ?BigDecimal $persen,
        public ?Uang $jumlah,
    ) {
        if (($persen === null) === ($jumlah === null)) {
            throw new InvalidArgumentException('Diskon manual harus berisi tepat satu: Persen atau Jumlah.');
        }
    }

    public function KePotongan(): DataPotongan
    {
        return $this->persen !== null ? DataPotongan::BuatPersen($this->persen) : DataPotongan::BuatNominal($this->jumlah ?? Uang::Nol());
    }

    /**
     * @return array{Persen: string}|array{Jumlah: string}
     */
    public function KeLarik(): array
    {
        return $this->persen !== null ? ['Persen' => (string) $this->persen] : ['Jumlah' => ($this->jumlah ?? Uang::Nol())->KeString()];
    }
}

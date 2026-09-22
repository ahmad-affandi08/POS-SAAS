<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Nilai;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Jumlah barang dengan presisi tetap 4 desimal (kg, meter, liter) (PRD §15.1, CLAUDE.md #7).
 */
final readonly class Kuantitas implements JsonSerializable, Stringable
{
    public const SKALA = 4;

    private function __construct(private BigDecimal $nilai) {}

    public static function Dari(BigNumber|int|string $nilai): self
    {
        $desimal = BigDecimal::of($nilai);

        if ($desimal->getScale() > self::SKALA && ! $desimal->toScale(self::SKALA, RoundingMode::Down)->isEqualTo($desimal)) {
            throw new InvalidArgumentException('Kuantitas maksimal '.self::SKALA." desimal: {$nilai}");
        }

        return new self($desimal->toScale(self::SKALA));
    }

    public static function Nol(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SKALA));
    }

    public function Tambah(self $lain): self
    {
        return new self($this->nilai->plus($lain->nilai));
    }

    public function Kurangi(self $lain): self
    {
        return new self($this->nilai->minus($lain->nilai));
    }

    /** Konversi satuan, misal 2 dus × 12 = 24 pcs. */
    public function Kali(BigNumber|int|string $faktor, RoundingMode $mode = RoundingMode::HalfUp): self
    {
        return new self($this->nilai->multipliedBy($faktor)->toScale(self::SKALA, $mode));
    }

    public function Negasi(): self
    {
        return new self($this->nilai->negated());
    }

    public function Bandingkan(self $lain): int
    {
        return $this->nilai->compareTo($lain->nilai);
    }

    public function SamaDengan(self $lain): bool
    {
        return $this->nilai->isEqualTo($lain->nilai);
    }

    public function BernilaiNegatif(): bool
    {
        return $this->nilai->isNegative();
    }

    public function KeDesimal(): BigDecimal
    {
        return $this->nilai;
    }

    public function KeString(): string
    {
        return (string) $this->nilai;
    }

    public function jsonSerialize(): string
    {
        return $this->KeString();
    }

    public function __toString(): string
    {
        return $this->KeString();
    }
}

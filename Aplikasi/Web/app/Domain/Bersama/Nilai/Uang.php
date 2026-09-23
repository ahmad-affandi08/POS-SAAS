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
 * Nilai uang Rupiah dengan presisi tetap 2 desimal (PRD §8 F-07, §15.1, CLAUDE.md #7).
 *
 * Tidak pernah memakai float. Masukan hanya int, string desimal, atau BigNumber.
 * Nilai dengan lebih dari 2 desimal ditolak, kecuali lewat operasi yang menyebut mode pembulatan.
 */
final readonly class Uang implements JsonSerializable, Stringable
{
    public const SKALA = 2;

    private function __construct(private BigDecimal $nilai) {}

    public static function Dari(BigNumber|int|string $nilai): self
    {
        $desimal = BigDecimal::of($nilai);

        if ($desimal->getScale() > self::SKALA && ! $desimal->toScale(self::SKALA, RoundingMode::Down)->isEqualTo($desimal)) {
            throw new InvalidArgumentException('Uang maksimal '.self::SKALA." desimal: {$nilai}");
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

    /**
     * Mengalikan dengan jumlah/tarif lalu membulatkan ke 2 desimal dengan mode yang disebut eksplisit.
     */
    public function Kali(BigNumber|int|string $faktor, RoundingMode $mode = RoundingMode::HalfUp): self
    {
        return new self($this->nilai->multipliedBy($faktor)->toScale(self::SKALA, $mode));
    }

    /**
     * Pembulatan ke kelipatan tertentu, misal pembulatan tunai ke Rp 100 (PRD Lampiran D).
     */
    public function BulatkanKeKelipatan(int $kelipatan, RoundingMode $mode): self
    {
        if ($kelipatan <= 0) {
            throw new InvalidArgumentException('Kelipatan pembulatan harus lebih dari 0.');
        }

        $dibulatkan = $this->nilai->dividedBy($kelipatan, 0, $mode)->multipliedBy($kelipatan);

        return new self($dibulatkan->toScale(self::SKALA));
    }

    public function Bandingkan(self $lain): int
    {
        return $this->nilai->compareTo($lain->nilai);
    }

    public function SamaDengan(self $lain): bool
    {
        return $this->nilai->isEqualTo($lain->nilai);
    }

    public function BernilaiNol(): bool
    {
        return $this->nilai->isZero();
    }

    public function BernilaiNegatif(): bool
    {
        return $this->nilai->isNegative();
    }

    /** Format penyimpanan & API: string desimal, misal "15000.00". */
    public function KeString(): string
    {
        return (string) $this->nilai;
    }

    /** Format tampilan Indonesia, misal "Rp 1.250.000" atau "−Rp 6.000". */
    public function FormatRupiah(): string
    {
        $mutlak = $this->nilai->abs();
        $bulat = $mutlak->toScale(0, RoundingMode::Down);
        $sen = (string) $mutlak->minus($bulat)->multipliedBy(100)->toScale(0);
        $teks = 'Rp '.number_format((int) (string) $bulat, 0, ',', '.');

        if ($sen !== '0') {
            $teks .= ','.str_pad($sen, 2, '0', STR_PAD_LEFT);
        }

        return ($this->nilai->isNegative() ? '−' : '').$teks;
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

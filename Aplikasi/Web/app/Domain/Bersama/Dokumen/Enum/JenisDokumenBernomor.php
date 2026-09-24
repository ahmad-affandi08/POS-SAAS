<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Enum;

use InvalidArgumentException;

/**
 * Jenis dokumen yang dinomori `PenomorDokumen` (`NomorUrutDokumen.JenisDokumen`, DesainF05a B.4). Nomor berurutan
 * tanpa celah per tenant, jenis, dan periode `YYYY-MM`: `SA/2026/09/0001`, `JU/2026/09/000001`.
 */
enum JenisDokumenBernomor: string
{
    case StokAwal = 'StokAwal';
    case Jurnal = 'Jurnal';

    public function AmbilAwalan(): string
    {
        return match ($this) {
            self::StokAwal => 'SA',
            self::Jurnal => 'JU',
        };
    }

    public function AmbilPanjangUrut(): int
    {
        return match ($this) {
            self::StokAwal => 4,
            self::Jurnal => 6,
        };
    }

    /**
     * @param  string  $periode  `YYYY-MM`
     *
     * @throws InvalidArgumentException bila periode tidak berformat `YYYY-MM` atau urut < 1
     */
    public function FormatNomor(string $periode, int $urut): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $periode, $cocok) !== 1 || $urut < 1) {
            throw new InvalidArgumentException("Periode {$periode} atau nomor urut {$urut} tidak valid.");
        }

        return sprintf('%s/%s/%s/%s', $this->AmbilAwalan(), $cocok[1], $cocok[2], str_pad((string) $urut, $this->AmbilPanjangUrut(), '0', STR_PAD_LEFT));
    }
}

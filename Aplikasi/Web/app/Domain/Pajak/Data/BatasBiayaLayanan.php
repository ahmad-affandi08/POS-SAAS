<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Data;

/**
 * Batas biaya layanan (service charge) outlet, PRD §12.1: 0 sampai 10 persen. Satu sumber untuk validasi template
 * sektor (P-03) dan pengaturan pajak outlet (F-01). String desimal, dibandingkan dengan BigDecimal (tidak float).
 */
final class BatasBiayaLayanan
{
    public const PERSEN_MAKSIMAL = '10';
}

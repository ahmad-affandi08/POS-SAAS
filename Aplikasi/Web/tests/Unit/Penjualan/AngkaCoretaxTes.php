<?php

declare(strict_types=1);

use App\Domain\Penjualan\Layanan\PenulisXmlCoretax;
use App\Domain\Penjualan\Layanan\PenyusunFakturPajakCoretax;
use Brick\Math\BigDecimal;

/*
 * Format angka XML Coretax (PRD v3.12) tanpa database. Regresi audit PAY-P0-02: pemanggilan `stripTrailingZeros()` &
 * `hasNonZeroFractionalPart()` (tidak ada di brick/math 1.x yang terkunci) membuat ekspor Coretax melempar Error.
 */
it('PenulisXmlCoretax::Angka membuang nol di belakang koma tanpa notasi ilmiah', function (string $masukan, string $harapan): void {
    expect(PenulisXmlCoretax::Angka($masukan))->toBe($harapan);
})->with([
    ['15000.00', '15000'],
    ['200.0000', '200'],
    ['2750000.00', '2750000'],
    ['12.000000', '12'],
    ['12.50', '12.5'],
    ['0.00', '0'],
    ['1234.5600', '1234.56'],
    ['0.0100', '0.01'],
]);

it('RingkasAngka konsisten dengan Angka dan tidak pernah bernotasi ilmiah', function (string $masukan, string $harapan): void {
    expect(PenyusunFakturPajakCoretax::RingkasAngka(BigDecimal::of($masukan)))->toBe($harapan);
})->with([
    ['200.0000', '200'],
    ['1000', '1000'],
    ['12.500000', '12.5'],
    ['0.0100', '0.01'],
]);

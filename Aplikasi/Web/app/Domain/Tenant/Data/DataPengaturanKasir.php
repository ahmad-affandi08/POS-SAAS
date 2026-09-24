<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use Brick\Math\BigDecimal;

/**
 * Pengaturan kasir tingkat tenant dari `Tenant.Pengaturan`:
 * - F-06 (PRD v1.34): batas kas keluar tanpa persetujuan (BR-06.4, bawaan Rp 200.000 sesuai §19.2) dan mode shift
 *   bersama (BR-06.2, bawaan mati).
 * - F-07b (PRD v1.43): batas diskon manual kasir dalam persen (BR-07.3, bawaan 10), batas diskon yang boleh disetujui
 *   penyetuju (bawaan 30; Pemilik tanpa batas), dan pembulatan tunai (BR-08.6; null = tanpa pembulatan).
 *   Persen = BigDecimal skala 2 (tidak pernah float).
 */
final readonly class DataPengaturanKasir
{
    public const BATAS_KAS_KELUAR_BAWAAN = '200000.00';

    public const BATAS_DISKON_MANUAL_BAWAAN = '10.00';

    public const BATAS_DISKON_PENYETUJU_BAWAAN = '30.00';

    public BigDecimal $batasDiskonManual;

    public BigDecimal $batasDiskonPenyetuju;

    /**
     * @param  array{Kelipatan: int, Arah: ArahPembulatan}|null  $pembulatanTunai
     */
    public function __construct(
        public Uang $batasKasKeluar,
        public bool $shiftBersama,
        BigDecimal|string|null $batasDiskonManual = null,
        BigDecimal|string|null $batasDiskonPenyetuju = null,
        public ?array $pembulatanTunai = null,
    ) {
        $this->batasDiskonManual = BigDecimal::of($batasDiskonManual ?? self::BATAS_DISKON_MANUAL_BAWAAN)->toScale(2);
        $this->batasDiskonPenyetuju = BigDecimal::of($batasDiskonPenyetuju ?? self::BATAS_DISKON_PENYETUJU_BAWAAN)->toScale(2);
    }

    /**
     * Bentuk JSON (`data-awal`, halaman pengaturan): `{Kelipatan, Arah}` atau null.
     *
     * @return array{Kelipatan: int, Arah: string}|null
     */
    public function AmbilPembulatanTunaiLarik(): ?array
    {
        return $this->pembulatanTunai === null ? null : ['Kelipatan' => $this->pembulatanTunai['Kelipatan'], 'Arah' => $this->pembulatanTunai['Arah']->value];
    }
}

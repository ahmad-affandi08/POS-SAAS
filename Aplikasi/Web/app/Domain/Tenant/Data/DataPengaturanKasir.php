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
 * - F-11 (PRD v1.45): tutup shift buta (bawaan aktif; kas seharusnya tidak ditampilkan sebelum hitungan disimpan)
 *   dan toleransi selisih kas (bawaan Rp 10.000, §19.2; di atasnya wajib alasan + PIN `shift.selisih.setujui`).
 * - F-09 (PRD v1.45): batas hari retur penjualan sejak tanggal bisnis penjualan (bawaan 7; 0 = hanya hari yang sama).
 */
final readonly class DataPengaturanKasir
{
    public const BATAS_KAS_KELUAR_BAWAAN = '200000.00';

    public const BATAS_DISKON_MANUAL_BAWAAN = '10.00';

    public const BATAS_DISKON_PENYETUJU_BAWAAN = '30.00';

    public const TOLERANSI_SELISIH_KAS_BAWAAN = '10000.00';

    public const BATAS_HARI_RETUR_BAWAAN = 7;

    /** Batas atas wajar batas hari retur (satu tahun). */
    public const BATAS_HARI_RETUR_MAKSIMAL = 365;

    public BigDecimal $batasDiskonManual;

    public BigDecimal $batasDiskonPenyetuju;

    /** F-11: |selisih| di atas nilai ini wajib alasan + penyetuju. */
    public Uang $toleransiSelisihKas;

    /**
     * @param  array{Kelipatan: int, Arah: ArahPembulatan}|null  $pembulatanTunai
     */
    public function __construct(
        public Uang $batasKasKeluar,
        public bool $shiftBersama,
        BigDecimal|string|null $batasDiskonManual = null,
        BigDecimal|string|null $batasDiskonPenyetuju = null,
        public ?array $pembulatanTunai = null,
        public bool $tutupShiftButa = true,
        ?Uang $toleransiSelisihKas = null,
        public int $batasHariRetur = self::BATAS_HARI_RETUR_BAWAAN,
    ) {
        $this->batasDiskonManual = BigDecimal::of($batasDiskonManual ?? self::BATAS_DISKON_MANUAL_BAWAAN)->toScale(2);
        $this->batasDiskonPenyetuju = BigDecimal::of($batasDiskonPenyetuju ?? self::BATAS_DISKON_PENYETUJU_BAWAAN)->toScale(2);
        $this->toleransiSelisihKas = $toleransiSelisihKas ?? Uang::Dari(self::TOLERANSI_SELISIH_KAS_BAWAAN);
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

<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use App\Domain\Tenant\Model\Tenant;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * Pengaturan kasir tenant aktif dari `Tenant.Pengaturan`: `BatasKasKeluar` (string desimal, bawaan Rp 200.000) dan
 * `ShiftBersama` (bawaan false) (F-06); `BatasDiskonManual` (persen, bawaan 10), `BatasDiskonPenyetuju` (persen,
 * bawaan 30), dan `PembulatanTunai {Kelipatan, Arah}` (diisi template sektor F-01; bawaan null = tanpa pembulatan)
 * (F-07b). Nilai rusak kembali ke bawaan.
 */
final class PengaturanKasirTenant
{
    /** Batas atas kelipatan pembulatan tunai (sama dengan validasi template sektor). */
    private const KELIPATAN_MAKSIMAL = 1000000;

    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Ambil(): DataPengaturanKasir
    {
        $pengaturan = Tenant::query()->whereKey($this->konteks->Wajib())->firstOrFail()->Pengaturan ?? [];
        $manual = self::AmbilPersen($pengaturan['BatasDiskonManual'] ?? null, DataPengaturanKasir::BATAS_DISKON_MANUAL_BAWAAN);
        $penyetuju = self::AmbilPersen($pengaturan['BatasDiskonPenyetuju'] ?? null, DataPengaturanKasir::BATAS_DISKON_PENYETUJU_BAWAAN);

        return new DataPengaturanKasir(
            self::AmbilBatas($pengaturan['BatasKasKeluar'] ?? null),
            ($pengaturan['ShiftBersama'] ?? false) === true,
            $manual,
            $penyetuju->isLessThan($manual) ? $manual : $penyetuju,
            self::AmbilPembulatan($pengaturan['PembulatanTunai'] ?? null),
        );
    }

    private static function AmbilBatas(mixed $nilai): Uang
    {
        if (is_string($nilai)) {
            try {
                $batas = Uang::Dari($nilai);

                if (! $batas->BernilaiNegatif()) {
                    return $batas;
                }
            } catch (MathException) {
                // Nilai tersimpan rusak: pakai bawaan.
            }
        }

        return Uang::Dari(DataPengaturanKasir::BATAS_KAS_KELUAR_BAWAAN);
    }

    /** Persen 0–100 dari string/bilangan bulat; selain itu bawaan. */
    private static function AmbilPersen(mixed $nilai, string $bawaan): BigDecimal
    {
        if (is_string($nilai) || is_int($nilai)) {
            try {
                $persen = BigDecimal::of($nilai);

                if (! $persen->isNegative() && $persen->isLessThanOrEqualTo(100) && $persen->getScale() <= 2) {
                    return $persen->toScale(2);
                }
            } catch (MathException) {
                // Nilai tersimpan rusak: pakai bawaan.
            }
        }

        return BigDecimal::of($bawaan)->toScale(2);
    }

    /**
     * @return array{Kelipatan: int, Arah: ArahPembulatan}|null
     */
    private static function AmbilPembulatan(mixed $nilai): ?array
    {
        if (! is_array($nilai)) {
            return null;
        }

        $kelipatan = $nilai['Kelipatan'] ?? null;
        $arah = is_string($nilai['Arah'] ?? null) ? ArahPembulatan::tryFrom($nilai['Arah']) : null;

        if (! is_int($kelipatan) || $kelipatan <= 0 || $kelipatan > self::KELIPATAN_MAKSIMAL || $arah === null) {
            return null;
        }

        return ['Kelipatan' => $kelipatan, 'Arah' => $arah];
    }
}

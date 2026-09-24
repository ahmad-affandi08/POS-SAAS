<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use App\Domain\Tenant\Model\Tenant;
use Brick\Math\Exception\MathException;

/**
 * Pengaturan kasir tenant aktif dari `Tenant.Pengaturan` (F-06): `BatasKasKeluar` (string desimal, bawaan
 * Rp 200.000) dan `ShiftBersama` (bawaan false). Nilai rusak kembali ke bawaan.
 */
final class PengaturanKasirTenant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Ambil(): DataPengaturanKasir
    {
        $pengaturan = Tenant::query()->whereKey($this->konteks->Wajib())->firstOrFail()->Pengaturan ?? [];

        return new DataPengaturanKasir(
            self::AmbilBatas($pengaturan['BatasKasKeluar'] ?? null),
            ($pengaturan['ShiftBersama'] ?? false) === true,
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
}

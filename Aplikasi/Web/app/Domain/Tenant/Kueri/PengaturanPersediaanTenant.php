<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Tenant\Data\DataPengaturanPersediaan;
use App\Domain\Tenant\Model\Tenant;
use Brick\Math\Exception\MathException;

/**
 * Pengaturan persediaan tenant aktif dari `Tenant.Pengaturan` (DesainF05a C.1): `MetodeHpp` (bawaan RataRata) dan
 * `StokBolehMinus` (bawaan false), serta F-05b `BatasPersetujuanPenyesuaian` (string desimal, bawaan Rp 500.000;
 * nilai rusak/negatif kembali ke bawaan). `AmbilDenganKunciBaca()` mengambil kunci S baris Tenant (kunci L1) sehingga
 * metode HPP tidak bisa diubah (kunci X `UbahPengaturanPersediaan`) selama mutasi stok berjalan. Hanya di dalam
 * transaksi.
 */
final class PengaturanPersediaanTenant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Ambil(): DataPengaturanPersediaan
    {
        return self::Petakan(Tenant::query()->whereKey($this->konteks->Wajib())->firstOrFail());
    }

    public function AmbilDenganKunciBaca(): DataPengaturanPersediaan
    {
        return self::Petakan(Tenant::query()->whereKey($this->konteks->Wajib())->sharedLock()->firstOrFail());
    }

    private static function Petakan(Tenant $tenant): DataPengaturanPersediaan
    {
        $pengaturan = $tenant->Pengaturan ?? [];
        $metode = is_string($pengaturan['MetodeHpp'] ?? null) ? MetodeHpp::tryFrom($pengaturan['MetodeHpp']) : null;

        return new DataPengaturanPersediaan(
            $metode ?? MetodeHpp::RataRata,
            ($pengaturan['StokBolehMinus'] ?? false) === true,
            self::AmbilBatas($pengaturan['BatasPersetujuanPenyesuaian'] ?? null),
        );
    }

    private static function AmbilBatas(mixed $nilai): ?Uang
    {
        if (! is_string($nilai)) {
            return null;
        }

        try {
            $batas = Uang::Dari($nilai);

            return $batas->BernilaiNegatif() ? null : $batas;
        } catch (MathException) {
            return null;
        }
    }
}

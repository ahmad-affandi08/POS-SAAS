<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Data\DataProfilPajakOutlet;
use App\Domain\Organisasi\Model\Outlet;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Pengaturan pajak outlet tersimpan (`Outlet.ProfilPajak`, F-01 langkah 3) dalam bentuk DTO untuk domain lain.
 * Kunci yang belum pernah diisi dianggap tidak aktif; persen biaya layanan = string desimal 2 angka (tidak float).
 */
final class ProfilPajakOutlet
{
    public function Ambil(int $idOutlet): ?DataProfilPajakOutlet
    {
        $outlet = Outlet::query()->whereKey($idOutlet)->first();

        if ($outlet === null) {
            return null;
        }

        $profil = $outlet->ProfilPajak ?? [];
        $biaya = is_array($profil['BiayaLayanan'] ?? null) ? $profil['BiayaLayanan'] : [];

        return new DataProfilPajakOutlet(
            pkp: ($profil['Pkp'] ?? false) === true,
            pungutPbjt: ($profil['PungutPbjt'] ?? false) === true,
            biayaLayananAktif: ($biaya['Aktif'] ?? false) === true,
            persenBiayaLayanan: self::AmbilPersen($biaya['Persen'] ?? null),
            hargaTermasukPajak: ($profil['HargaTermasukPajak'] ?? false) === true,
        );
    }

    private static function AmbilPersen(mixed $nilai): string
    {
        try {
            $persen = is_string($nilai) || is_int($nilai) ? BigDecimal::of($nilai) : BigDecimal::zero();
        } catch (MathException) {
            $persen = BigDecimal::zero();
        }

        return (string) $persen->toScale(2, RoundingMode::Down);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Tenant\Enum\StatusMitra;
use App\Domain\Tenant\Model\AtribusiMitra;
use App\Domain\Tenant\Model\Mitra;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * P-12 langkah 3: tenant yang mendaftar lewat tautan mitra dicatat sebagai rujukan mitra itu bila klik pertamanya
 * belum lewat `HARI_BERLAKU` hari dan mitranya aktif. BR-P12.2: tenant yang sudah teratribusi tidak dipindahkan.
 * Kode tak dikenal atau kedaluwarsa diabaikan diam-diam — pendaftaran tidak boleh gagal karena tautan rujukan.
 */
final class CatatAtribusiMitra
{
    public const HARI_BERLAKU = 90;

    public function Jalankan(int $idTenant, string $kodeMitra, ?CarbonImmutable $diklikPada): ?AtribusiMitra
    {
        $sekarang = CarbonImmutable::now();

        if ($diklikPada !== null && $diklikPada->lessThan($sekarang->subDays(self::HARI_BERLAKU))) {
            return null;
        }

        $mitra = Mitra::query()->where('Kode', strtoupper(trim($kodeMitra)))->where('Status', StatusMitra::Aktif->value)->first();

        if (! $mitra instanceof Mitra || AtribusiMitra::query()->where('IdTenant', $idTenant)->exists()) {
            return null;
        }

        try {
            return AtribusiMitra::query()->create([
                'IdMitra' => $mitra->Id,
                'IdTenant' => $idTenant,
                'Sumber' => AtribusiMitra::SUMBER_TAUTAN,
                'DiklikPada' => $diklikPada,
                'MulaiPada' => $sekarang,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}

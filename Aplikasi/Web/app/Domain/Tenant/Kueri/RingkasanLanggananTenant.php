<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use Carbon\CarbonImmutable;

/**
 * Status langganan tenant aktif untuk banner back-office dan pembatasan saat `Ditangguhkan` (F-00, BR-00.7).
 * `BatasTenggangPada` = saat langganan `Tertunggak` akan ditangguhkan (akhir periode + masa tenggang).
 */
final class RingkasanLanggananTenant
{
    /**
     * @return array{Status: StatusLangganan, PeriodeSelesai: CarbonImmutable|null, BatasTenggangPada: CarbonImmutable|null}|null
     */
    public function Ambil(int $idTenant): ?array
    {
        $langganan = Langganan::query()->where('IdTenant', $idTenant)->first(['Id', 'Status', 'PeriodeSelesai']);

        if ($langganan === null) {
            return null;
        }

        $periodeSelesai = $langganan->PeriodeSelesai === null ? null : CarbonImmutable::instance($langganan->PeriodeSelesai);

        return [
            'Status' => $langganan->Status,
            'PeriodeSelesai' => $periodeSelesai,
            'BatasTenggangPada' => $langganan->Status === StatusLangganan::Tertunggak && $periodeSelesai !== null
                ? $periodeSelesai->addDays((int) config('tagihan.HariMasaTenggang'))
                : null,
        ];
    }

    public function CekDitangguhkan(int $idTenant): bool
    {
        return Langganan::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusLangganan::Ditangguhkan->value)
            ->exists();
    }
}

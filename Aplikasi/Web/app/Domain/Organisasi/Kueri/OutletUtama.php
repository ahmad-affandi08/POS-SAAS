<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Data\DataOutletRingkas;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Outlet yang dikerjakan panduan awal (F-01): outlet aktif ber-Id terkecil (biasanya "Outlet Utama" dari F-00),
 * kecuali progres panduan sudah menunjuk outlet aktif lain.
 */
final class OutletUtama
{
    public function AmbilId(): ?int
    {
        $id = Outlet::query()->where('Status', StatusOrganisasi::Aktif->value)->orderBy('Id')->value('Id');

        return is_int($id) ? $id : null;
    }

    /** Outlet aktif dengan Id tertentu di tenant aktif; null bila tidak ada atau diarsipkan. */
    public function CariAktif(?int $idOutlet): ?Outlet
    {
        return $idOutlet === null ? null : Outlet::query()->whereKey($idOutlet)->where('Status', StatusOrganisasi::Aktif->value)->first();
    }

    /** Sama dengan `CariAktif`, dalam bentuk DTO untuk domain lain. */
    public function CariAktifRingkas(?int $idOutlet): ?DataOutletRingkas
    {
        $outlet = $this->CariAktif($idOutlet);

        return $outlet === null ? null : DataOutletRingkas::DariModel($outlet);
    }
}

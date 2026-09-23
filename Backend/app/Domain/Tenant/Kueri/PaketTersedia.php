<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Paket;

/**
 * Paket yang bisa dipilih tenant baru (F-00): hanya berstatus Aktif (BR-P04.2).
 */
final class PaketTersedia
{
    /**
     * @return list<Paket>
     */
    public function AmbilUntukPendaftaran(): array
    {
        return array_values(Paket::query()->where('Status', StatusPaket::Aktif->value)->orderBy('Urutan')->get()->all());
    }
}

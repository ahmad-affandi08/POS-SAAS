<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Outlet;

/** Kode template sektor yang dipakai outlet tenant aktif (P-10 PGL-19: sasaran pengumuman per sektor). */
final class SektorOutletTenant
{
    /** @return list<string> */
    public function AmbilKode(): array
    {
        return array_values(array_unique(array_map(
            'strval',
            Outlet::query()->whereNotNull('TemplateSektor')->pluck('TemplateSektor')->all(),
        )));
    }
}

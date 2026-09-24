<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use LogicException;

/**
 * Detail satu jurnal (tipe FE `PropsDetailJurnal`, DesainF05a C.5/E); null bila tidak ada di tenant aktif.
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DetailJurnal
{
    /**
     * @return array<string, mixed>|null
     */
    public function Ambil(string $uuid): ?array
    {
        throw new LogicException('F-05a Tim B');
    }
}

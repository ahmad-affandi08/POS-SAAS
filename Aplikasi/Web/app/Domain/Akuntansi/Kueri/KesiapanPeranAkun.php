<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\PeranAkun;
use LogicException;

/**
 * Kesiapan pemetaan peran akun yang dibutuhkan sebuah posting (DesainF05a C.5, tipe FE `KesiapanAkun`).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class KesiapanPeranAkun
{
    /**
     * @param  list<PeranAkun>  $peran
     * @return array{Siap: bool, PeranBelumDipetakan: list<array{Kunci: string, Label: string}>}
     */
    public function Periksa(array $peran): array
    {
        throw new LogicException('F-05a Tim B');
    }
}

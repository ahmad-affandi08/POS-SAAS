<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use LogicException;

/**
 * Id akun untuk peran akun: pemetaan outlet dulu, lalu pemetaan tenant; tipe akun harus cocok (DesainF05a C.5).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PenentuAkun
{
    public function AmbilIdAkun(PeranAkun $peran, ?int $idOutlet): int
    {
        throw new LogicException('F-05a Tim B');
    }
}

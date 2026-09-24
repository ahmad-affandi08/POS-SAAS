<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Data\HasilPostingJurnal;
use LogicException;

/**
 * Posting jurnal seimbang, idempoten per (JenisSumber, IdSumber, KunciSumber) (J-05.1, §11.1, DesainF05a C.5).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class PostingJurnal
{
    public function Jalankan(DataJurnal $data): HasilPostingJurnal
    {
        throw new LogicException('F-05a Tim B');
    }
}

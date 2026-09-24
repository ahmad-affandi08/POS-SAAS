<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\HasilPostingJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Jurnal pembalik cermin persis dari jurnal asal, dengan IdJurnalDibalik terisi (DesainF05a C.5).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim B (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class BalikkanJurnal
{
    public function Jalankan(int $idJurnal, CarbonImmutable $tanggal, string $keterangan, JenisSumberJurnal $jenis, int $idSumber, string $kunciSumber, ?int $idPengguna): HasilPostingJurnal
    {
        throw new LogicException('F-05a Tim B');
    }
}

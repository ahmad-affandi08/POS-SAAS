<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Jurnal;

/**
 * Jurnal sumber yang masih berlaku: jurnal terbaru dokumen sumber yang bukan pembalik dan belum dibalik. Dipakai
 * domain lain (lewat kueri publik, aturan #14) untuk membalik jurnal dokumennya, misal K-18 buka ulang shift membalik
 * jurnal selisih kas tutup sebelumnya.
 */
final class JurnalSumberAktif
{
    public function AmbilId(JenisSumberJurnal $jenis, int $idSumber): ?int
    {
        $jurnal = Jurnal::query()
            ->where('JenisSumber', $jenis->value)
            ->where('IdSumber', $idSumber)
            ->whereNull('IdJurnalDibalik')
            ->whereNotExists(fn ($kueri) => $kueri->from('Jurnal', 'Pembalik')->whereColumn('Pembalik.IdJurnalDibalik', 'Jurnal.Id'))
            ->orderByDesc('Id')
            ->first(['Id']);

        return $jurnal?->Id;
    }
}

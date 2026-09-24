<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Jurnal;

/**
 * Ringkasan jurnal satu dokumen sumber untuk halaman detail dokumen domain lain (F-05a, tipe FE
 * `PropsDetailStokAwal['Jurnal']`), urut pencatatan. `Pembalik` = jurnal ini membalik jurnal lain. API baca publik
 * Akuntansi supaya domain sumber tidak membaca tabel Jurnal langsung (§13.3).
 */
final class JurnalSumber
{
    /**
     * @return list<array{Uuid: string, Nomor: string, Tanggal: string, Keterangan: string, TotalDebit: string, Pembalik: bool}>
     */
    public function Ambil(JenisSumberJurnal $jenis, int $idSumber): array
    {
        return array_values(Jurnal::query()
            ->where('JenisSumber', $jenis->value)
            ->where('IdSumber', $idSumber)
            ->orderBy('Id')
            ->get()
            ->map(fn (Jurnal $j): array => [
                'Uuid' => $j->Uuid,
                'Nomor' => $j->Nomor,
                'Tanggal' => $j->Tanggal->format('Y-m-d'),
                'Keterangan' => $j->Keterangan,
                'TotalDebit' => $j->TotalDebit,
                'Pembalik' => $j->IdJurnalDibalik !== null,
            ])
            ->all());
    }
}

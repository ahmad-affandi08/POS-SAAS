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
        return $this->Cari($jenis, $idSumber)?->Id;
    }

    /**
     * Nomor & Uuid jurnal yang masih berlaku untuk tautan di halaman detail dokumen sumber.
     *
     * @return array{Uuid: string, Nomor: string}|null
     */
    public function AmbilRingkas(JenisSumberJurnal $jenis, int $idSumber): ?array
    {
        $jurnal = $this->Cari($jenis, $idSumber);

        return $jurnal === null ? null : ['Uuid' => $jurnal->Uuid, 'Nomor' => $jurnal->Nomor];
    }

    private function Cari(JenisSumberJurnal $jenis, int $idSumber): ?Jurnal
    {
        return Jurnal::query()
            ->where('JenisSumber', $jenis->value)
            ->where('IdSumber', $idSumber)
            ->whereNull('IdJurnalDibalik')
            ->whereNotExists(fn ($kueri) => $kueri->from('Jurnal', 'Pembalik')->whereColumn('Pembalik.IdJurnalDibalik', 'Jurnal.Id'))
            ->orderByDesc('Id')
            ->first(['Id', 'Uuid', 'Nomor']);
    }
}

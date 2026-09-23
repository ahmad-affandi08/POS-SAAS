<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Model\TarifPajak;
use Carbon\CarbonInterface;

/**
 * Tarif pajak master yang berlaku pada satu tanggal (dipakai kalkulasi F-07 dan sinkron POS). Hanya status Terbit.
 */
final class TarifPajakBerlaku
{
    public function Cari(string $kodeJenisPajak, ?string $kodeWilayah, CarbonInterface $tanggal): ?TarifPajak
    {
        $hari = $tanggal->toDateString();

        return TarifPajak::query()
            ->whereHas('JenisPajak', fn ($jenis) => $jenis->where('Kode', $kodeJenisPajak))
            ->where('Status', StatusDataMaster::Terbit->value)
            ->where('KodeWilayah', $kodeWilayah)
            ->whereDate('BerlakuMulai', '<=', $hari)
            ->where(fn ($kueri) => $kueri->whereNull('BerlakuSampai')->orWhereDate('BerlakuSampai', '>=', $hari))
            ->orderByDesc('BerlakuMulai')
            ->first();
    }
}

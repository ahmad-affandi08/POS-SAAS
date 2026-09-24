<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Data\DataInfoShift;
use App\Domain\Kasir\Model\Shift;
use Carbon\CarbonImmutable;

/**
 * API baca publik shift (F-07b): shift tenant aktif per Uuid milik perangkat tertentu, dan ringkasan per Id untuk
 * tampilan. `CariDiPerangkat` dipanggil di dalam transaksi penjualan dan memegang kunci bersama baris shift sehingga
 * serial dengan tutup shift (F-11, kunci eksklusif).
 */
final class InfoShift
{
    public function CariDiPerangkat(string $uuid, int $idPerangkat): ?DataInfoShift
    {
        $shift = Shift::query()->where('Uuid', $uuid)->where('IdPerangkat', $idPerangkat)->sharedLock()->first();

        return $shift === null ? null : self::Petakan($shift);
    }

    /**
     * @param  list<int>  $id
     * @return array<int, DataInfoShift> kunci = Id
     */
    public function AmbilBanyak(array $id): array
    {
        $hasil = [];

        foreach (Shift::query()->whereIn('Id', array_values(array_unique($id)))->get() as $shift) {
            $hasil[$shift->Id] = self::Petakan($shift);
        }

        return $hasil;
    }

    /** Id shift tenant aktif dari Uuid, null bila tidak ada. */
    public function CariId(string $uuid): ?int
    {
        $id = Shift::query()->where('Uuid', $uuid)->value('Id');

        return is_int($id) ? $id : null;
    }

    private static function Petakan(Shift $shift): DataInfoShift
    {
        return new DataInfoShift($shift->Id, $shift->Uuid, $shift->IdOutlet, $shift->IdPerangkat, CarbonImmutable::parse($shift->DibukaPada), $shift->Status->CekAktif());
    }
}

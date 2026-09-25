<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Outlet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Selisih zona waktu outlet dari UTC dalam detik (F-14a laporan per jam). Zona Indonesia (WIB/WITA/WIT) tidak
 * mengenal musim panas, sehingga selisih pada saat ini berlaku untuk seluruh periode laporan.
 */
final class ZonaWaktuOutlet
{
    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet tenant
     * @return array<int, int> Id outlet → selisih detik
     */
    public function AmbilSelisihDetik(?array $idOutlet): array
    {
        $hasil = [];
        $sekarang = CarbonImmutable::now();

        foreach (Outlet::query()->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('Id', $idOutlet ?? []))->get(['Id', 'ZonaWaktu']) as $outlet) {
            $hasil[$outlet->Id] = $sekarang->setTimezone($outlet->ZonaWaktu)->utcOffset() * 60;
        }

        return $hasil;
    }
}

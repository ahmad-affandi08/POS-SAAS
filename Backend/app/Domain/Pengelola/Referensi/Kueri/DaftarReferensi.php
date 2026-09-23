<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Kueri;

use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Model\ReferensiBank;
use App\Domain\Referensi\Model\SatuanStandar;
use App\Domain\Referensi\Model\Wilayah;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kueri daftar halaman referensi Platform Pengelola (P-02).
 */
final class DaftarReferensi
{
    private const PER_HALAMAN = 50;

    /**
     * @return LengthAwarePaginator<int, Wilayah>
     */
    public function CariWilayah(string $kata, ?TingkatWilayah $tingkat): LengthAwarePaginator
    {
        return Wilayah::query()
            ->when($kata !== '', fn (Builder $kueri) => $kueri->where(
                fn (Builder $dalam) => $dalam->where('Nama', 'like', self::BuatPolaLike($kata))->orWhere('Kode', 'like', $kata.'%'),
            ))
            ->when($tingkat !== null, fn (Builder $kueri) => $kueri->where('Tingkat', $tingkat?->value))
            ->orderBy('Kode')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman')
            ->withQueryString();
    }

    /**
     * @return list<ReferensiBank>
     */
    public function AmbilSemuaBank(): array
    {
        return array_values(ReferensiBank::query()->orderBy('Jenis')->orderBy('Nama')->get()->all());
    }

    /**
     * @return list<SatuanStandar>
     */
    public function AmbilSemuaSatuan(): array
    {
        return array_values(SatuanStandar::query()->orderBy('Nama')->get()->all());
    }

    private static function BuatPolaLike(string $kata): string
    {
        return '%'.addcslashes($kata, '%_\\').'%';
    }
}

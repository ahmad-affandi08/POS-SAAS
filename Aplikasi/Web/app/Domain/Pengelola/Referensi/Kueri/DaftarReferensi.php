<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Model\ReferensiBank;
use App\Domain\Referensi\Model\SatuanStandar;
use App\Domain\Referensi\Model\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Kueri daftar halaman referensi Platform Pengelola (P-02).
 */
final class DaftarReferensi
{
    public const KOLOM_URUT_WILAYAH = ['Kode', 'Nama'];

    public const KOLOM_SARING_WILAYAH = ['Tingkat'];

    /**
     * Wilayah untuk `TabelData` (D-16): cari nama (mengandung) atau kode (awalan); saring tingkat (pilihan banyak).
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabelWilayah(DataPermintaanTabel $permintaan): array
    {
        $kata = $permintaan->cari;
        $tingkat = $permintaan->AmbilDaftar('Tingkat', array_map(fn (TingkatWilayah $item): string => $item->value, TingkatWilayah::cases()));
        $kueri = Wilayah::query()
            ->when($kata !== '', fn (Builder $kueri) => $kueri->where(
                fn (Builder $dalam) => $dalam->where('Nama', 'like', PenerapKueriTabel::PolaCari($kata))->orWhere('Kode', 'like', addcslashes($kata, '%_\\').'%'),
            ))
            ->when($tingkat !== [], fn (Builder $kueri) => $kueri->whereIn('Tingkat', $tingkat));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Kode' => 'Kode', 'Nama' => 'Nama'], fn (Collection $wilayah): array => array_values($wilayah->map(fn (Wilayah $item): array => [
            'Kode' => $item->Kode,
            'Nama' => $item->Nama,
            'Tingkat' => $item->Tingkat->value,
            'KodeInduk' => $item->KodeInduk,
            'ZonaWaktu' => $item->ZonaWaktu->value,
        ])->all()));
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
}

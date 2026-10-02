<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;

/**
 * X7 Open API v1 (`GET /api/v1/pelanggan`, cakupan `pelanggan:baca`): pelanggan tenant aktif urut `Id`, halaman
 * berbasis kursor. Data identitas pajak (NPWP/NIK terenkripsi), alamat, catatan internal, dan limit kredit tidak
 * dikirim (minimisasi data, UU PDP); nomor HP & email dikirim karena itulah kegunaan integrasi CRM yang diberi izin
 * pemilik usaha.
 */
final class PelangganUntukApiPublik
{
    /**
     * @return array{Data: list<array<string, mixed>>, IdTerakhir: int|null}
     */
    public function Daftar(int $setelahId, int $batas, ?string $uuid = null): array
    {
        $pelanggan = Pelanggan::query()
            ->when($uuid !== null, fn ($k) => $k->where('Uuid', $uuid), fn ($k) => $k->where('Id', '>', $setelahId))
            ->orderBy('Id')
            ->limit($batas)
            ->get(['Id', 'Uuid', 'Nama', 'NoHp', 'Email', 'TanggalLahir', 'Tag', 'SetujuPemasaran', 'Status', 'IdTier', 'DibuatPada', 'DiubahPada']);
        $tier = TierPelanggan::query()->whereIn('Id', $pelanggan->pluck('IdTier')->filter()->unique()->values()->all())->pluck('Nama', 'Id');

        return [
            'Data' => array_values($pelanggan->map(fn (Pelanggan $p): array => [
                'Uuid' => $p->Uuid,
                'Nama' => $p->Nama,
                'NoHp' => $p->NoHp,
                'Email' => $p->Email,
                'TanggalLahir' => $p->TanggalLahir?->toDateString(),
                'Tag' => $p->Tag ?? [],
                'SetujuPemasaran' => $p->SetujuPemasaran,
                'Status' => $p->Status->value,
                'Tier' => $p->IdTier === null ? null : $tier->get($p->IdTier),
                'DibuatPada' => $p->DibuatPada?->toIso8601ZuluString(),
                'DiubahPada' => $p->DiubahPada?->toIso8601ZuluString(),
            ])->all()),
            'IdTerakhir' => $pelanggan->count() < $batas ? null : $pelanggan->last()?->Id,
        ];
    }

    /**
     * Uuid pelanggan per Id (untuk penjualan di API publik).
     *
     * @param  list<int>  $id
     * @return array<int, string>
     */
    public function PetaUuid(array $id): array
    {
        return $id === [] ? [] : Pelanggan::query()->whereIn('Id', array_values(array_unique($id)))->pluck('Uuid', 'Id')->all();
    }
}

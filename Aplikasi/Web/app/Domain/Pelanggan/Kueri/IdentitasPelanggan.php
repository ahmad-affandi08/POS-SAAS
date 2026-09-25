<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PelangganAlias;
use App\Domain\Pelanggan\Model\TierPelanggan;

/**
 * Kueri publik domain Pelanggan (F-16a): menemukan pelanggan dari Uuid yang dikirim POS (termasuk alias Uuid
 * perangkat untuk nomor HP yang sudah terdaftar). Dipakai penerimaan `Penjualan.Buat`.
 */
final class IdentitasPelanggan
{
    public function CariId(string $uuid): ?int
    {
        $id = Pelanggan::query()->where('Uuid', $uuid)->value('Id');

        if ($id !== null) {
            return (int) $id;
        }

        $alias = PelangganAlias::query()->where('Uuid', $uuid)->value('IdPelanggan');

        return $alias === null ? null : (int) $alias;
    }

    /** F-16c: kode tier pelanggan (syarat promo); null = tanpa tier. */
    public function AmbilKodeTier(?int $id): ?string
    {
        $idTier = $id === null ? null : Pelanggan::query()->whereKey($id)->value('IdTier');

        return $idTier === null ? null : TierPelanggan::query()->whereKey($idTier)->value('Kode');
    }

    /**
     * Uuid & nama pelanggan untuk tampilan dokumen (detail penjualan).
     *
     * @return array{Uuid: string, Nama: string}|null
     */
    public function AmbilRingkas(?int $id): ?array
    {
        $p = $id === null ? null : Pelanggan::query()->whereKey($id)->first(['Uuid', 'Nama']);

        return $p === null ? null : ['Uuid' => $p->Uuid, 'Nama' => $p->Nama];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PelangganAlias;
use App\Domain\Pelanggan\Model\TierPelanggan;

/**
 * Kueri publik domain Pelanggan (F-16a): menemukan pelanggan dari Uuid yang dikirim POS (termasuk alias Uuid
 * perangkat untuk nomor HP yang sudah terdaftar). Dipakai penerimaan `Penjualan.Buat`.
 */
final class IdentitasPelanggan
{
    public function __construct(private readonly CariPelangganPos $cariPos) {}

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

    /** Tanggal lahir `YYYY-MM-DD` untuk promo ulang tahun (F-16c bagian 3); null bila tidak diisi/tidak dikenal. */
    public function AmbilTanggalLahir(?int $id): ?string
    {
        $pelanggan = $id === null ? null : Pelanggan::query()->whereKey($id)->first(['Id', 'TanggalLahir']);

        return $pelanggan?->TanggalLahir?->toDateString();
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

    /**
     * F-12 bagian 2: identitas pelanggan pre-order untuk POS (nomor HP tersamar, tier untuk harga) per Id.
     *
     * @param  list<int>  $id
     * @return array<int, array{Uuid: string, Nama: string, NoHp: string, KodeTier: string|null, NamaTier: string|null}>
     */
    public function AmbilUntukPos(array $id): array
    {
        $hasil = [];
        $daftar = $id === [] ? collect() : Pelanggan::query()->whereKey(array_values(array_unique($id)))->get(['Id', 'Uuid', 'Nama', 'NoHp', 'IdTier']);
        $tier = TierPelanggan::query()->whereKey(array_values(array_filter($daftar->pluck('IdTier')->all(), 'is_int')))->get(['Id', 'Kode', 'Nama'])->keyBy('Id');

        foreach ($daftar as $p) {
            $t = $p->IdTier === null ? null : $tier->get($p->IdTier);
            $hasil[$p->Id] = ['Uuid' => $p->Uuid, 'Nama' => $p->Nama, 'NoHp' => NomorHp::Samarkan($p->NoHp), 'KodeTier' => $t?->Kode, 'NamaTier' => $t?->Nama];
        }

        return $hasil;
    }

    /**
     * F-12 bagian 2: Id pelanggan aktif yang nama/nomor HP-nya cocok dengan [kata] (cari pre-order di POS), maks. 20.
     *
     * @return list<int>
     */
    public function CariIdPos(string $kata): array
    {
        return array_values(array_map('intval', array_filter(array_map(
            fn (array $p): ?int => $this->CariId($p['Uuid']),
            $this->cariPos->Cari($kata),
        ))));
    }
}

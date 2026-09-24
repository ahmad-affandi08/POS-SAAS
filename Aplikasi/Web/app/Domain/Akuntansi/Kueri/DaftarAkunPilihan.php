<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Model\Akun;

/**
 * API baca publik akun tenant aktif untuk domain lain yang memetakan sesuatu ke akun (F-06 kategori kas): pilihan
 * dropdown per tipe dan pencarian per Uuid, tanpa domain lain membaca tabel `Akun`.
 *
 * @phpstan-type BarisAkun array{Id: int, Uuid: string, Kode: string, Nama: string, Jenis: string}
 */
final class DaftarAkunPilihan
{
    /**
     * @param  list<TipeAkun>  $tipe
     * @return list<BarisAkun>
     */
    public function Ambil(array $tipe): array
    {
        return array_values(Akun::query()
            ->whereIn('Jenis', array_map(fn (TipeAkun $t): string => $t->value, $tipe))
            ->orderBy('Kode')
            ->get()
            ->map(fn (Akun $a): array => self::Petakan($a))
            ->all());
    }

    /**
     * Akun dari Uuid bila tipenya termasuk `$tipe`; null bila tidak ada, milik tenant lain, atau tipenya lain.
     *
     * @param  list<TipeAkun>  $tipe
     * @return BarisAkun|null
     */
    public function CariDariUuid(string $uuid, array $tipe): ?array
    {
        $akun = Akun::query()->where('Uuid', $uuid)->first();

        return $akun !== null && in_array($akun->Jenis, $tipe, true) ? self::Petakan($akun) : null;
    }

    /**
     * @param  list<int>  $id
     * @return array<int, BarisAkun> kunci = Id
     */
    public function AmbilBanyak(array $id): array
    {
        $hasil = [];

        foreach (Akun::query()->whereIn('Id', array_values(array_unique($id)))->get() as $akun) {
            $hasil[$akun->Id] = self::Petakan($akun);
        }

        return $hasil;
    }

    /**
     * @return BarisAkun
     */
    private static function Petakan(Akun $akun): array
    {
        return ['Id' => $akun->Id, 'Uuid' => $akun->Uuid, 'Kode' => $akun->Kode, 'Nama' => $akun->Nama, 'Jenis' => $akun->Jenis->value];
    }
}

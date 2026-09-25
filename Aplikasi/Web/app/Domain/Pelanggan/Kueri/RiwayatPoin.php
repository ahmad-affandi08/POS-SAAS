<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Model\MutasiPoin;

/** Riwayat buku poin satu pelanggan (F-16b), terbaru dulu, maks. [batas] baris. */
final class RiwayatPoin
{
    /**
     * @return list<array{Id: int, Jenis: string, LabelJenis: string, Poin: int, Sisa: int|null, KedaluwarsaPada: string|null, Keterangan: string|null, DibuatPada: string|null}>
     */
    public function Ambil(int $idPelanggan, int $batas = 100): array
    {
        return array_values(MutasiPoin::query()
            ->where('IdPelanggan', $idPelanggan)
            ->orderByDesc('Id')
            ->limit($batas)
            ->get()
            ->map(fn (MutasiPoin $m): array => [
                'Id' => $m->Id,
                'Jenis' => $m->Jenis->value,
                'LabelJenis' => $m->Jenis->AmbilLabel(),
                'Poin' => $m->Poin,
                'Sisa' => $m->Sisa,
                'KedaluwarsaPada' => $m->KedaluwarsaPada?->toDateString(),
                'Keterangan' => $m->Keterangan,
                'DibuatPada' => $m->DibuatPada?->toIso8601ZuluString(),
            ])->all());
    }
}

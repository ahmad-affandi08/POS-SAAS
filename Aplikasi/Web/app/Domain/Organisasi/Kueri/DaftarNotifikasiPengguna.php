<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\NotifikasiPengguna;

/** Pusat notifikasi Aplikasi Owner: terbaru dulu, hanya milik pengguna pada tenant aktif. */
final class DaftarNotifikasiPengguna
{
    public const BATAS = 100;

    /** @return array{Notifikasi: list<array<string, mixed>>, BelumDibaca: int} */
    public function Ambil(int $idPengguna): array
    {
        $dasar = NotifikasiPengguna::query()->where('IdPengguna', $idPengguna);
        $belum = (clone $dasar)->whereNull('DibacaPada')->count();
        $daftar = $dasar->orderByDesc('DibuatPada')->orderByDesc('Id')->limit(self::BATAS)->get();

        return [
            'Notifikasi' => array_values($daftar->map(fn (NotifikasiPengguna $n): array => [
                'Uuid' => $n->Uuid,
                'Jenis' => $n->Jenis->value,
                'Judul' => $n->Judul,
                'Isi' => $n->Isi,
                'Data' => $n->Data ?? (object) [],
                'DibacaPada' => $n->DibacaPada?->toIso8601ZuluString(),
                'DibuatPada' => $n->DibuatPada?->toIso8601ZuluString(),
            ])->all()),
            'BelumDibaca' => $belum,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Model\NotifikasiPengguna;

/** Menandai notifikasi milik penerima; Uuid asing diabaikan agar tidak membocorkan keberadaan data tenant lain. */
final class TandaiNotifikasiDibaca
{
    /** @param list<string> $uuid */
    public function Jalankan(int $idPengguna, array $uuid, bool $semua): int
    {
        $kueri = NotifikasiPengguna::query()->where('IdPengguna', $idPengguna)->whereNull('DibacaPada');

        if (! $semua) {
            $kueri->whereIn('Uuid', array_values(array_unique(array_map('strtoupper', $uuid))));
        }

        return $kueri->update(['DibacaPada' => now()]);
    }
}

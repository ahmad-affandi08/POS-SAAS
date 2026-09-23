<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Model\Perangkat;

/**
 * Memperbarui `TerakhirAktifPada` dan `VersiAplikasi` (header `X-Versi-Aplikasi`) setiap permintaan API POS
 * (F-02b, dipakai P-11 & dasbor perangkat). Ditulis paling sering sekali per menit agar tidak membebani database,
 * kecuali versi aplikasi berubah.
 */
final class PencatatAktivitasPerangkat
{
    private const DETIK_JEDA = 60;

    public function Catat(Perangkat $perangkat, ?string $versiAplikasi): void
    {
        $versiBaru = $versiAplikasi !== null && $versiAplikasi !== '' && $versiAplikasi !== $perangkat->VersiAplikasi;
        $sudahLama = $perangkat->TerakhirAktifPada === null || $perangkat->TerakhirAktifPada->lt(now()->subSeconds(self::DETIK_JEDA));

        if (! $versiBaru && ! $sudahLama) {
            return;
        }

        $perangkat->TerakhirAktifPada = now();

        if ($versiBaru) {
            $perangkat->VersiAplikasi = mb_substr($versiAplikasi, 0, 30);
        }

        $perangkat->save();
    }
}

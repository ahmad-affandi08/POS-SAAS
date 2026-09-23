<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-01 langkah 2: membuat/menyelaraskan tujuh peran internal bawaan beserta izinnya (PRD §19.3). Idempoten.
 */
final class SiapkanPeranBawaan
{
    public function Jalankan(): void
    {
        DB::transaction(function (): void {
            foreach (PeranPengelolaBawaan::cases() as $bawaan) {
                $peran = PeranPengelola::query()->updateOrCreate(
                    ['Kode' => $bawaan->value],
                    ['Nama' => $bawaan->Nama(), 'Bawaan' => true],
                );

                $kunciIzin = array_map(fn ($izin) => $izin->value, $bawaan->Izin());
                $peran->Izin()->whereNotIn('KunciIzin', $kunciIzin)->delete();

                foreach ($kunciIzin as $kunci) {
                    $peran->Izin()->firstOrCreate(['KunciIzin' => $kunci]);
                }
            }
        });
    }
}

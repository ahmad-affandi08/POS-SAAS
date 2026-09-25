<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Pelanggan\Enum\JenisMutasiPoin;
use App\Domain\Pelanggan\Enum\SumberMutasiPoin;
use App\Domain\Pelanggan\Model\MutasiPoin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menghanguskan sisa poin yang masa berlakunya lewat (F-16b) untuk tenant aktif: setiap lot dengan `KedaluwarsaPada`
 * sebelum [hariIni] dan `Sisa` > 0 mendapat baris `Kedaluwarsa` negatif (sumber `Sistem`, IdSumber = Id lot, sehingga
 * idempoten) dan `Sisa`-nya menjadi 0. Hasil: jumlah poin yang dihanguskan.
 */
final class HanguskanPoinKedaluwarsa
{
    public function Jalankan(CarbonImmutable $hariIni): int
    {
        $total = 0;

        MutasiPoin::query()
            ->where('Sisa', '>', 0)
            ->whereNotNull('KedaluwarsaPada')
            ->where('KedaluwarsaPada', '<', $hariIni->toDateString())
            ->orderBy('Id')
            ->pluck('Id')
            ->chunk(200)
            ->each(function ($daftar) use (&$total): void {
                DB::transaction(function () use ($daftar, &$total): void {
                    foreach (MutasiPoin::query()->whereKey($daftar->all())->lockForUpdate()->get() as $lot) {
                        $sisa = (int) $lot->Sisa;

                        if ($sisa <= 0) {
                            continue;
                        }

                        MutasiPoin::query()->create([
                            'IdPelanggan' => $lot->IdPelanggan,
                            'Jenis' => JenisMutasiPoin::Kedaluwarsa,
                            'Poin' => -$sisa,
                            'Sisa' => null,
                            'JenisSumber' => SumberMutasiPoin::Sistem,
                            'IdSumber' => $lot->Id,
                            'Keterangan' => 'Poin berakhir '.$lot->KedaluwarsaPada?->toDateString(),
                        ]);
                        $lot->Sisa = 0;
                        $lot->save();
                        $total += $sisa;
                    }
                });
            });

        return $total;
    }
}

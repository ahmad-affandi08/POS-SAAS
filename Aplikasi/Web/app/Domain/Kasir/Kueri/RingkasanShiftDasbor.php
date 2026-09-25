<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\Shift;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan shift untuk dasbor pemilik (F-14a): shift yang masih terbuka (termasuk dibuka ulang & sedang menutup)
 * dan shift tertutup terbaru beserta selisih kasnya.
 */
final class RingkasanShiftDasbor
{
    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet
     * @return array{JumlahTerbuka: int, Terbuka: list<array{Uuid: string, IdOutlet: int, DibukaOleh: int, DibukaPada: string}>, Tertutup: list<array{Uuid: string, IdOutlet: int, DitutupOleh: int|null, DitutupPada: string|null, KasSeharusnya: string|null, KasAktual: string|null, Selisih: string|null}>}
     */
    public function Ambil(?array $idOutlet, int $batas = 5): array
    {
        $dasar = fn (): Builder => Shift::query()->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []));
        $statusTerbuka = [StatusShift::Terbuka->value, StatusShift::DibukaUlang->value, StatusShift::Menutup->value];
        $terbuka = $dasar()->whereIn('Status', $statusTerbuka)->orderByDesc('DibukaPada')->orderByDesc('Id')->limit($batas)->get();
        $tertutup = $dasar()->where('Status', StatusShift::Tertutup->value)->orderByDesc('DitutupPada')->orderByDesc('Id')->limit($batas)->get();

        return [
            'JumlahTerbuka' => $dasar()->whereIn('Status', $statusTerbuka)->count(),
            'Terbuka' => array_values($terbuka->map(fn (Shift $s): array => [
                'Uuid' => $s->Uuid,
                'IdOutlet' => $s->IdOutlet,
                'DibukaOleh' => $s->DibukaOleh,
                'DibukaPada' => $s->DibukaPada->toIso8601String(),
            ])->all()),
            'Tertutup' => array_values($tertutup->map(fn (Shift $s): array => [
                'Uuid' => $s->Uuid,
                'IdOutlet' => $s->IdOutlet,
                'DitutupOleh' => $s->DitutupOleh,
                'DitutupPada' => $s->DitutupPada?->toIso8601String(),
                'KasSeharusnya' => $s->KasSeharusnya,
                'KasAktual' => $s->KasAktual,
                'Selisih' => $s->Selisih,
            ])->all()),
        ];
    }
}

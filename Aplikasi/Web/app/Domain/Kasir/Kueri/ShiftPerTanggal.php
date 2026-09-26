<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\Shift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * OWN-02/OWN-05: shift pada satu tanggal bisnis (laporan shift & selisih kas Aplikasi Owner), urut waktu buka.
 * `Selisih` null selama shift belum (atau sedang dibuka ulang dan belum) ditutup.
 */
final class ShiftPerTanggal
{
    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet
     * @return list<array{Uuid: string, IdOutlet: int, DibukaOleh: int, DitutupOleh: int|null, DibukaPada: string, DitutupPada: string|null, Status: string, Selisih: string|null}>
     */
    public function Ambil(CarbonInterface $tanggalBisnis, ?array $idOutlet): array
    {
        if ($idOutlet === []) {
            return [];
        }

        return array_values(Shift::query()
            ->where('TanggalBisnis', $tanggalBisnis->toDateString())
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->orderBy('DibukaPada')
            ->orderBy('Id')
            ->get()
            ->map(fn (Shift $s): array => [
                'Uuid' => $s->Uuid,
                'IdOutlet' => $s->IdOutlet,
                'DibukaOleh' => $s->DibukaOleh,
                'DitutupOleh' => $s->DitutupOleh,
                'DibukaPada' => $s->DibukaPada->utc()->toIso8601ZuluString(),
                'DitutupPada' => $s->DitutupPada?->utc()->toIso8601ZuluString(),
                'Status' => $s->Status->value,
                'Selisih' => $s->Status === StatusShift::Tertutup ? $s->Selisih : null,
            ])
            ->all());
    }
}

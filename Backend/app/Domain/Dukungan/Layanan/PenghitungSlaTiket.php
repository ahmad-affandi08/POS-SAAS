<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Layanan;

use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use Illuminate\Support\Carbon;

/**
 * SLA respons pertama tiket (P-09 "SLA per paket"): jam dari `config('dukungan.SlaResponsPertamaJam')` per kode
 * paket lalu per prioritas. Fase 0 memakai jam kalender; jam kerja & hari libur (P-02) menyusul di Fase 2.
 */
final class PenghitungSlaTiket
{
    public function HitungJam(?string $kodePaket, PrioritasTiketDukungan $prioritas): int
    {
        /** @var array<string, array<string, int>> $perPaket */
        $perPaket = (array) config('dukungan.SlaResponsPertamaJam');
        /** @var array<string, int> $bawaan */
        $bawaan = (array) config('dukungan.SlaResponsPertamaJamBawaan');
        $tabel = $kodePaket !== null && isset($perPaket[$kodePaket]) ? $perPaket[$kodePaket] : $bawaan;

        return max(1, (int) ($tabel[$prioritas->value] ?? $bawaan[$prioritas->value] ?? 48));
    }

    public function HitungBatas(Carbon $dibuatPada, int $jam): Carbon
    {
        return $dibuatPada->copy()->addHours($jam);
    }
}

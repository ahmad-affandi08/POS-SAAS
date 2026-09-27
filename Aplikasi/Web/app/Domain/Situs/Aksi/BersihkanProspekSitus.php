<?php

declare(strict_types=1);

namespace App\Domain\Situs\Aksi;

use App\Domain\Situs\Enum\StatusProspek;
use App\Domain\Situs\Model\ProspekSitus;

/**
 * Situs pemasaran bagian B: retensi data prospek (UU PDP, data tidak disimpan lebih lama dari perlu). Prospek Spam
 * dihapus setelah 30 hari; prospek lain setelah 24 bulan sejak masuk.
 */
final class BersihkanProspekSitus
{
    public const BULAN_SIMPAN = 24;

    public const HARI_SIMPAN_SPAM = 30;

    public function Jalankan(): int
    {
        $spam = ProspekSitus::query()
            ->where('Status', StatusProspek::Spam->value)
            ->where('DibuatPada', '<', now()->subDays(self::HARI_SIMPAN_SPAM))
            ->delete();

        $lama = ProspekSitus::query()->where('DibuatPada', '<', now()->subMonths(self::BULAN_SIMPAN))->delete();

        return (int) $spam + (int) $lama;
    }
}

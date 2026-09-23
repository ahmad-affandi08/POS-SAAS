<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Model\PenghapusanKatalog;

/**
 * Mencatat jejak penghapusan baris katalog (tenant aktif) untuk bagian `Terhapus` katalog POS (F-03). Dipanggil di
 * transaksi yang sama dengan penghapusannya.
 */
final class PencatatPenghapusanKatalog
{
    public function Catat(EntitasKatalog $entitas, string $uuid): void
    {
        PenghapusanKatalog::query()->create([
            'Entitas' => $entitas,
            'UuidEntitas' => $uuid,
            'DihapusPada' => now(),
        ]);
    }
}

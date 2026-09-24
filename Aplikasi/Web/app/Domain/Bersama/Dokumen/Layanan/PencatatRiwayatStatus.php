<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Layanan;

use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;

/**
 * Mencatat satu perubahan status dokumen transaksi ke `RiwayatStatusDokumen` (§13.3, DesainF05a C.1). Dipanggil
 * Aksi di transaksi yang sama dengan perubahan statusnya. `dari` null = dokumen baru dibuat.
 */
final class PencatatRiwayatStatus
{
    public function Catat(string $jenisDokumen, int $idDokumen, ?string $dari, string $ke, ?int $oleh, ?string $alasan = null): void
    {
        RiwayatStatusDokumen::query()->create([
            'JenisDokumen' => $jenisDokumen,
            'IdDokumen' => $idDokumen,
            'StatusDari' => $dari,
            'StatusKe' => $ke,
            'Alasan' => $alasan,
            'DiubahOleh' => $oleh,
        ]);
    }
}

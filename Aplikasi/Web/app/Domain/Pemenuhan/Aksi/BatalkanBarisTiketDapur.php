<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Pemenuhan\Enum\StatusBarisTiket;
use App\Domain\Pemenuhan\Model\TiketDapurDetail;

/**
 * F-10b (BR-07.5): item pesanan yang di-void setelah dikirim ke dapur ditandai `Dibatalkan` di tiketnya agar dapur
 * berhenti membuatnya. Dipanggil di transaksi pembatalan baris pesanan.
 */
final class BatalkanBarisTiketDapur
{
    /**
     * @param  list<string>  $uuidBaris
     */
    public function Jalankan(array $uuidBaris): int
    {
        if ($uuidBaris === []) {
            return 0;
        }

        return TiketDapurDetail::query()->whereIn('UuidBaris', $uuidBaris)->where('Status', StatusBarisTiket::Aktif->value)
            ->update(['Status' => StatusBarisTiket::Dibatalkan->value, 'DiubahPada' => now()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Peristiwa;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tagihan langganan lunas lewat gerbang pembayaran platform (BR-P08.11). Dikirim setelah commit; penangannya
 * mengirim email pemberitahuan ke Owner di antrean, jadi webhook bisa dijawab 200 tanpa menunggu SMTP.
 *
 * Membawa data siap pakai (bukan id) dengan alasan yang sama seperti `TiketDukunganDibuat`: penangannya berada di
 * domain Pengelola dan tidak perlu membaca data tenant lagi.
 */
final class TagihanLanggananDilunasiGerbang implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $idTenant,
        public readonly string $nomorTagihan,
        public readonly string $email,
        public readonly string $nama,
        public readonly string $total,
        public readonly string $namaPaket,
        public readonly string $periodeSelesai,
    ) {}
}

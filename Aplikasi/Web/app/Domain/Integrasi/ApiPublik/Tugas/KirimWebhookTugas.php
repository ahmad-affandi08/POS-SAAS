<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Tugas;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Aksi\KirimKirimanWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * X7 bagian 2: kirim satu webhook di antrean (efek non-kritis, aturan #10). Satu percobaan saja: coba ulang dijadwalkan
 * lewat `KirimanWebhook.BerikutnyaPada` dan diambil perintah `integrasi:kirim-webhook` tiap menit, bukan backoff antrean.
 * Payload hanya `IdTenant` & `IdKiriman`.
 */
final class KirimWebhookTugas implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idKiriman,
    ) {}

    public function handle(KonteksTenant $konteks, KirimKirimanWebhook $kirim): void
    {
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($this->idTenant);

        try {
            $kirim->Jalankan($this->idKiriman);
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Tugas;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pelanggan\Layanan\PengirimKampanyePesan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * CRM-07: satu giliran kirim kampanye di antrean (efek non-kritis, aturan #10). Selama masih ada penerima, tugas
 * mengantrekan dirinya lagi dengan jeda dari `PengirimKampanyePesan` (tidak di antrean `sync`). Payload hanya `IdTenant` &
 * `IdKampanye`.
 */
final class KirimKampanyePesanTugas implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idKampanye,
    ) {}

    public function handle(KonteksTenant $konteks, PengirimKampanyePesan $pengirim): void
    {
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($this->idTenant);

        try {
            $jeda = $pengirim->KirimGiliran($this->idKampanye);
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        // Antrean `sync` (lokal/test) mengabaikan jeda: mengantrekan ulang di sana berarti berputar tanpa henti saat jam
        // tenang. Giliran berikutnya baru jalan di antrean sungguhan.
        if ($jeda !== null && $this->connection !== 'sync' && config('queue.default') !== 'sync') {
            self::dispatch($this->idTenant, $this->idKampanye)->delay(now()->addSeconds($jeda));
        }
    }
}

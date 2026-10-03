<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Penangan;

use App\Domain\Bersama\Peristiwa\PeristiwaIntegrasi;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;
use App\Domain\Integrasi\ApiPublik\Tugas\AntrekanWebhookIntegrasiTugas;

/**
 * X7 §16.4 (v3.91): `PeristiwaIntegrasi` dari domain mana pun → kiriman webhook bagi webhook aktif tenant yang
 * melanggan. Berjalan sinkron setelah commit (peristiwa `ShouldDispatchAfterCommit`) hanya untuk memeriksa langganan;
 * penyusunan data & pencatatan kiriman di `AntrekanWebhookIntegrasiTugas` (antrean). v3.98: tanpa webhook yang
 * melanggan, tidak ada tugas antrean sama sekali (perubahan produk dari impor besar tidak membanjiri antrean).
 */
final class AntrekanWebhookIntegrasi
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function handle(PeristiwaIntegrasi $peristiwa): void
    {
        if (PeristiwaWebhook::tryFrom($peristiwa->jenis) === null || ! $this->CekAdaPelanggan($peristiwa)) {
            return;
        }

        AntrekanWebhookIntegrasiTugas::dispatch($peristiwa->idTenant, $peristiwa->jenis, $peristiwa->idDokumen, $peristiwa->kunci, $peristiwa->data);
    }

    private function CekAdaPelanggan(PeristiwaIntegrasi $peristiwa): bool
    {
        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($peristiwa->idTenant);

        try {
            return WebhookTenant::query()->where('Aktif', true)->get()
                ->contains(fn (WebhookTenant $w): bool => $w->CekBerlangganan($peristiwa->jenis));
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }
    }
}

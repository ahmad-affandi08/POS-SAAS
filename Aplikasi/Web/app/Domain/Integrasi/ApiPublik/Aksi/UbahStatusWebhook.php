<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;

/** X7 bagian 2: aktifkan/nonaktifkan webhook. Webhook nonaktif tidak menerima peristiwa baru; kiriman tertunda jadi Gagal. */
final class UbahStatusWebhook
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(WebhookTenant $webhook, bool $aktif): void
    {
        if ($webhook->Aktif === $aktif) {
            return;
        }

        $webhook->forceFill(['Aktif' => $aktif])->save();
        $this->audit->Catat('integrasi.webhook.ubah-status', $webhook, ['Aktif' => ! $aktif], ['Aktif' => $aktif]);
    }
}

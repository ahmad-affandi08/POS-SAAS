<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;

/** X7 bagian 2: hapus webhook (soft delete; log kiriman tetap terbaca sampai masa retensi). */
final class HapusWebhook
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(WebhookTenant $webhook): void
    {
        $webhook->delete();
        $this->audit->Catat('integrasi.webhook.hapus', $webhook, ['Nama' => $webhook->Nama, 'Url' => $webhook->Url], null);
    }
}

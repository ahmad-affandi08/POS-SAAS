<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu peristiwa untuk satu webhook (X7 bagian 2). `Uuid` = `IdPeristiwa` (dedup di penerima). `Muatan` dibekukan saat
 * peristiwa terjadi sehingga kirim ulang mengirim isi yang sama persis.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdWebhookTenant
 * @property string $Peristiwa
 * @property int $IdDokumen
 * @property array<string, mixed> $Muatan
 * @property StatusKirimanWebhook $Status
 * @property int $Percobaan
 * @property Carbon|null $BerikutnyaPada
 * @property int|null $KodeRespons
 * @property string|null $CuplikanRespons
 * @property Carbon|null $TerkirimPada
 * @property Carbon|null $DibuatPada
 * @property-read WebhookTenant $Webhook
 */
final class KirimanWebhook extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KirimanWebhook';

    /** @var array<string, mixed> */
    protected $attributes = ['Percobaan' => 0, 'BerikutnyaPada' => null, 'KodeRespons' => null, 'CuplikanRespons' => null, 'TerkirimPada' => null];

    /**
     * @return BelongsTo<WebhookTenant, $this>
     */
    public function Webhook(): BelongsTo
    {
        return $this->belongsTo(WebhookTenant::class, 'IdWebhookTenant', 'Id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Muatan' => 'array',
            'Status' => StatusKirimanWebhook::class,
            'BerikutnyaPada' => 'datetime',
            'TerkirimPada' => 'datetime',
        ];
    }
}

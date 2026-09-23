<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\SiklusTagihan;
use App\Domain\Tenant\Enum\StatusLangganan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Langganan satu tenant (F-00, BR-00.3, BR-00.7). Satu baris per tenant; perubahan status hanya lewat transisi sah.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPaket
 * @property StatusLangganan $Status
 * @property StatusLangganan|null $StatusSebelumDitangguhkan diisi saat ditangguhkan manual (P-07), dipulihkan saat diaktifkan kembali
 * @property Carbon|null $TrialBerakhirPada
 * @property Carbon|null $PeriodeMulai
 * @property Carbon|null $PeriodeSelesai
 * @property SiklusTagihan $SiklusTagihan
 * @property-read Paket $Paket
 * @property-read Tenant $Tenant
 */
final class Langganan extends ModelDasar
{
    protected $table = 'Langganan';

    /** @var array<string, mixed> */
    protected $attributes = [
        'StatusSebelumDitangguhkan' => null,
        'TrialBerakhirPada' => null,
        'PeriodeMulai' => null,
        'PeriodeSelesai' => null,
        'SiklusTagihan' => 'Bulanan',
    ];

    protected static function booted(): void
    {
        self::updating(static function (Langganan $langganan): void {
            $asal = $langganan->getOriginal('Status');

            if ($langganan->isDirty('Status') && $asal instanceof StatusLangganan && ! $asal->BisaBerubahKe($langganan->Status)) {
                throw new LogicException("Langganan {$asal->value} tidak bisa berubah menjadi {$langganan->Status->value} (BR-00.7).");
            }
        });
    }

    /**
     * @return BelongsTo<Paket, $this>
     */
    public function Paket(): BelongsTo
    {
        return $this->belongsTo(Paket::class, 'IdPaket', 'Id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function Tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'IdTenant', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusLangganan::class,
            'StatusSebelumDitangguhkan' => StatusLangganan::class,
            'TrialBerakhirPada' => 'datetime',
            'PeriodeMulai' => 'datetime',
            'PeriodeSelesai' => 'datetime',
            'SiklusTagihan' => SiklusTagihan::class,
        ];
    }
}

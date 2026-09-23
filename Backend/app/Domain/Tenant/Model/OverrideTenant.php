<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\JenisOverride;
use Illuminate\Support\Carbon;

/**
 * Override batas/fitur sementara dan jejak perpanjangan trial (P-07, PRD §15.3). Ditulis Platform Pengelola, dibaca
 * `SumberFiturTenant` untuk EvaluatorFitur. Sengaja tanpa `MilikTenant` (data platform, tidak terlihat tenant).
 * Berakhir otomatis: baris dengan `BerakhirPada` yang sudah lewat diabaikan. Baris tidak pernah dihapus.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property JenisOverride $Jenis
 * @property string $Kunci
 * @property string|null $Nilai
 * @property Carbon $BerakhirPada
 * @property string $Alasan
 * @property int $DibuatOleh
 * @property Carbon $DibuatPada
 */
final class OverrideTenant extends ModelDasar
{
    protected $table = 'OverrideTenant';

    /** @var array<string, mixed> */
    protected $attributes = ['Nilai' => null];

    public function CekAktif(): bool
    {
        return $this->BerakhirPada->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisOverride::class,
            'BerakhirPada' => 'datetime',
        ];
    }
}

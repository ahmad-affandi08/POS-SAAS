<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pajak dalam kelompok pajak tenant (PRD §12.2, §15.3). Merujuk `JenisPajak`; tarif efektif dicari saat
 * transaksi lewat `TarifPajakBerlaku` per kota outlet & tanggal (CLAUDE.md #12). `IdTarifPajak` = override tenant
 * (F-03), null di F-01.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdKelompokPajak
 * @property int $IdJenisPajak
 * @property int|null $IdTarifPajak
 * @property DasarPengenaanPajak $DasarPengenaan
 * @property int $Urutan
 * @property-read JenisPajak $JenisPajak
 */
final class KelompokPajakDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KelompokPajakDetail';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['IdTarifPajak' => null];

    /**
     * @return BelongsTo<JenisPajak, $this>
     */
    public function JenisPajak(): BelongsTo
    {
        return $this->belongsTo(JenisPajak::class, 'IdJenisPajak', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DasarPengenaan' => DasarPengenaanPajak::class, 'Urutan' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Template sektor (P-03, PRD §5.1). Isinya berversi di `TemplateSektorVersi`; F-01 menerapkan versi terbit.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Kode
 * @property string $Nama
 * @property string|null $Keterangan
 * @property-read TemplateSektorVersi|null $VersiTerbit
 * @property-read TemplateSektorVersi|null $VersiDraf
 */
final class TemplateSektor extends ModelDasar
{
    protected $table = 'TemplateSektor';

    /** @var array<string, mixed> */
    protected $attributes = ['Keterangan' => null];

    /**
     * @return HasMany<TemplateSektorVersi, $this>
     */
    public function Versi(): HasMany
    {
        return $this->hasMany(TemplateSektorVersi::class, 'IdTemplateSektor', 'Id');
    }

    /**
     * @return HasOne<TemplateSektorVersi, $this>
     */
    public function VersiTerbit(): HasOne
    {
        return $this->hasOne(TemplateSektorVersi::class, 'IdTemplateSektor', 'Id')
            ->where('Status', StatusTemplateSektor::Terbit->value);
    }

    /**
     * @return HasOne<TemplateSektorVersi, $this>
     */
    public function VersiDraf(): HasOne
    {
        return $this->hasOne(TemplateSektorVersi::class, 'IdTemplateSektor', 'Id')
            ->where('Status', StatusTemplateSektor::Draf->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemetaan peran akun ke akun COA tenant (PRD §11.1, §11.3), dipakai `AturanPosting` (F-13). `Kunci` = nilai
 * `PeranAkun`; `IdOutlet` null = tingkat tenant.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $Kunci
 * @property int $IdAkun
 * @property int|null $IdOutlet
 * @property-read Akun $Akun
 */
final class PemetaanAkun extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PemetaanAkun';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['IdOutlet' => null];

    /**
     * @return BelongsTo<Akun, $this>
     */
    public function Akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'IdAkun', 'Id');
    }
}

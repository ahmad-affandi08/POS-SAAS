<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan pengguna di tenant (BR-00.1). Sengaja tanpa `MilikTenant`: tabel ini dibaca lintas tenant untuk
 * menentukan tenant mana yang boleh dipilih pengguna setelah masuk, selalu disaring dengan `IdPengguna` miliknya.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property bool $Pemilik
 * @property string|null $HashPin
 * @property StatusKeanggotaan $Status
 * @property-read Pengguna $Pengguna
 */
final class TenantPengguna extends ModelDasar
{
    protected $table = 'TenantPengguna';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $hidden = ['HashPin'];

    /** @var array<string, mixed> */
    protected $attributes = ['Pemilik' => false, 'HashPin' => null, 'Status' => 'Aktif'];

    /**
     * @return BelongsTo<Pengguna, $this>
     */
    public function Pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'IdPengguna', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Pemilik' => 'boolean', 'Status' => StatusKeanggotaan::class];
    }
}

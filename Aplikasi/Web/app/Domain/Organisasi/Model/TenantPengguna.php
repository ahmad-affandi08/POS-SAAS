<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Keanggotaan pengguna di tenant (BR-00.1, F-02). Sengaja tanpa `MilikTenant`: tabel ini dibaca lintas tenant untuk
 * menentukan tenant mana yang boleh dipilih pengguna setelah masuk, selalu disaring dengan `IdPengguna` miliknya;
 * di back-office selalu disaring `IdTenant` tenant aktif secara eksplisit.
 *
 * `IdPeran` = peran utama di tenant (izin back-office). `Pemilik` selaras dengan peran bawaan Pemilik.
 * `SemuaOutlet` = akses ke semua outlet; selain itu akses lewat `OutletPengguna`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property bool $Pemilik
 * @property int|null $IdPeran
 * @property bool $SemuaOutlet
 * @property string|null $HashPin
 * @property string|null $VerifierPinOffline
 * @property StatusKeanggotaan $Status
 * @property Carbon|null $DinonaktifkanPada
 * @property-read Pengguna $Pengguna
 */
final class TenantPengguna extends ModelDasar
{
    protected $table = 'TenantPengguna';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $hidden = ['HashPin', 'VerifierPinOffline'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Pemilik' => false,
        'IdPeran' => null,
        'SemuaOutlet' => false,
        'HashPin' => null,
        'Status' => 'Aktif',
        'DinonaktifkanPada' => null,
    ];

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
        return [
            'Pemilik' => 'boolean',
            'VerifierPinOffline' => 'encrypted',
            'SemuaOutlet' => 'boolean',
            'Status' => StatusKeanggotaan::class,
            'DinonaktifkanPada' => 'datetime',
        ];
    }
}

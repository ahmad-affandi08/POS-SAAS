<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\PenandaTenant;
use App\Domain\Tenant\Enum\StatusTenant;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Akun usaha (F-00, PRD §15.3). Tabel induk isolasi data: tabel data tenant memakai `MilikTenant` dengan kolom
 * `IdTenant` yang merujuk tabel ini.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Nama
 * @property string $Slug
 * @property string|null $Npwp
 * @property bool $Pkp
 * @property string $ZonaWaktu
 * @property array<string, mixed>|null $Pengaturan
 * @property StatusTenant $Status
 * @property PenandaTenant|null $Penanda Uji/Demo/Internal, dikecualikan dari metrik bisnis & tagihan (P-07)
 * @property Carbon $DibuatPada
 * @property-read Langganan|null $Langganan
 */
final class Tenant extends ModelDasar
{
    protected $table = 'Tenant';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Npwp' => null,
        'Pkp' => false,
        'ZonaWaktu' => 'Asia/Jakarta',
        'Pengaturan' => null,
        'Status' => 'Aktif',
        'Penanda' => null,
    ];

    /**
     * @return HasOne<Langganan, $this>
     */
    public function Langganan(): HasOne
    {
        return $this->hasOne(Langganan::class, 'IdTenant', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Pkp' => 'boolean',
            'Pengaturan' => 'array',
            'Status' => StatusTenant::class,
            'Penanda' => PenandaTenant::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\AplikasiRilis;
use App\Domain\Tenant\Enum\KanalRilis;
use App\Domain\Tenant\Enum\StatusRilis;
use Illuminate\Support\Carbon;

/**
 * Rilis aplikasi Flutter per platform & kanal (P-10, §14.6). Data platform (tanpa `MilikTenant`); dikelola dari
 * Platform Pengelola, dibaca `VersiAplikasiPerangkat` untuk `konfigurasi-aplikasi`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property AplikasiRilis $Aplikasi
 * @property string $Platform
 * @property KanalRilis $Kanal
 * @property string $Versi
 * @property int|null $Build
 * @property StatusRilis $Status
 * @property int $PersenRollout
 * @property string|null $UrlUnduh
 * @property string|null $CatatanRilis
 * @property string|null $VersiMinimum
 * @property Carbon|null $VersiMinimumBerlakuPada
 * @property bool $PerbaikanKeamanan
 * @property Carbon|null $DiterbitkanPada
 * @property Carbon|null $DihentikanPada
 * @property string|null $AlasanDihentikan
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class RilisAplikasi extends ModelDasar
{
    protected $table = 'RilisAplikasi';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Build' => null,
        'PersenRollout' => 0,
        'UrlUnduh' => null,
        'CatatanRilis' => null,
        'VersiMinimum' => null,
        'VersiMinimumBerlakuPada' => null,
        'PerbaikanKeamanan' => false,
        'DiterbitkanPada' => null,
        'DihentikanPada' => null,
        'AlasanDihentikan' => null,
        'DibuatOleh' => null,
    ];

    /** Versi semantik tanpa `+BUILD` (§14.6): < 0 bila `$a` lebih lama dari `$b`. */
    public static function BandingkanVersi(string $a, string $b): int
    {
        return version_compare(explode('+', $a)[0], explode('+', $b)[0]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Aplikasi' => AplikasiRilis::class,
            'Kanal' => KanalRilis::class,
            'Status' => StatusRilis::class,
            'Build' => 'integer',
            'PersenRollout' => 'integer',
            'PerbaikanKeamanan' => 'boolean',
            'VersiMinimumBerlakuPada' => 'datetime',
            'DiterbitkanPada' => 'datetime',
            'DihentikanPada' => 'datetime',
        ];
    }
}

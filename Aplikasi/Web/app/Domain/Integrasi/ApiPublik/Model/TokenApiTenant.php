<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Token API publik tenant (X7 bagian 1). `HashToken` = SHA-256 bagian rahasia token; token asli tidak disimpan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property string $Prefiks
 * @property string $HashToken
 * @property list<string> $Cakupan
 * @property int $DibuatOleh
 * @property Carbon|null $TerakhirDipakaiPada
 * @property Carbon|null $KedaluwarsaPada
 * @property Carbon|null $DicabutPada
 * @property int|null $DicabutOleh
 * @property Carbon|null $DibuatPada
 */
final class TokenApiTenant extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TokenApiTenant';

    /** @var list<string> */
    protected $hidden = ['HashToken'];

    /** @var array<string, mixed> */
    protected $attributes = ['TerakhirDipakaiPada' => null, 'KedaluwarsaPada' => null, 'DicabutPada' => null, 'DicabutOleh' => null];

    public static function BuatHashToken(string $rahasia): string
    {
        return hash('sha256', $rahasia);
    }

    public function CekBerlaku(): bool
    {
        return $this->DicabutPada === null && ($this->KedaluwarsaPada === null || $this->KedaluwarsaPada->isFuture());
    }

    public function CekCakupan(string $cakupan): bool
    {
        return in_array($cakupan, $this->Cakupan, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Cakupan' => 'array',
            'TerakhirDipakaiPada' => 'datetime',
            'KedaluwarsaPada' => 'datetime',
            'DicabutPada' => 'datetime',
        ];
    }
}

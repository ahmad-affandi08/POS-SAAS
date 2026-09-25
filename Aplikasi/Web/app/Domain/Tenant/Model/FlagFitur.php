<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\CakupanFlagFitur;
use Illuminate\Support\Carbon;

/**
 * Aturan flag fitur (P-10, PGL-18). Data platform (tanpa `MilikTenant`); dikelola Platform Pengelola, dievaluasi
 * `FlagFiturTenant` untuk `EvaluatorFitur` dan `konfigurasi-aplikasi`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Kunci
 * @property CakupanFlagFitur $Cakupan
 * @property int|null $IdObjek
 * @property bool $Nilai
 * @property int|null $Persen
 * @property string $Alasan
 * @property int|null $DiubahOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class FlagFitur extends ModelDasar
{
    protected $table = 'FlagFitur';

    /** @var array<string, mixed> */
    protected $attributes = ['IdObjek' => null, 'Persen' => null, 'DiubahOleh' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Cakupan' => CakupanFlagFitur::class,
            'IdObjek' => 'integer',
            'Nilai' => 'boolean',
            'Persen' => 'integer',
        ];
    }
}

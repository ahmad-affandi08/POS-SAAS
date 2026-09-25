<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\CakupanKomisi;
use App\Domain\Karyawan\Enum\JenisKomisi;
use App\Domain\Karyawan\Enum\StatusAturanKomisi;

/**
 * Aturan komisi (F-18, EMP-04). `UuidProduk`/`UuidKategori` merujuk katalog lewat Uuid publik (tanpa FK lintas domain).
 * `Nilai` persen (0–100) atau Rupiah per jumlah. `LevelStaf` null = semua level.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property CakupanKomisi $Cakupan
 * @property string|null $UuidProduk
 * @property string|null $UuidKategori
 * @property string|null $LevelStaf
 * @property JenisKomisi $Jenis
 * @property string $Nilai
 * @property StatusAturanKomisi $Status
 */
final class AturanKomisi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'AturanKomisi';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Aktif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Cakupan' => CakupanKomisi::class,
            'Jenis' => JenisKomisi::class,
            'Nilai' => 'decimal:2',
            'Status' => StatusAturanKomisi::class,
        ];
    }
}

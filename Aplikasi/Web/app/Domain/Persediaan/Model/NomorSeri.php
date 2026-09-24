<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use Illuminate\Support\Carbon;

/**
 * Nomor seri unik per produk (F-05h, DesainF05a B.2). `IdGudang` terisi hanya saat `Tersedia`/di lokasi stok.
 * `IdPenjualanDetail` diisi F-07.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdProduk
 * @property string $Nomor
 * @property StatusNomorSeri $Status
 * @property int|null $IdGudang
 * @property int|null $IdPenjualanDetail
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class NomorSeri extends ModelDasar
{
    use MilikTenant;

    protected $table = 'NomorSeri';

    /** @var array<string, mixed> */
    protected $attributes = ['IdGudang' => null, 'IdPenjualanDetail' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Status' => StatusNomorSeri::class];
    }
}

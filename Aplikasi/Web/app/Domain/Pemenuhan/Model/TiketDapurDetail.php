<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pemenuhan\Enum\StatusBarisTiket;

/**
 * Baris tiket dapur: salinan nama produk, jumlah, pilihan (nama saja), dan catatan dari baris dokumen asal
 * (`UuidBaris`).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdTiketDapur
 * @property string $UuidBaris
 * @property string $NamaProduk
 * @property string $Jumlah
 * @property list<string>|null $Pilihan
 * @property string|null $Catatan
 * @property StatusBarisTiket $Status
 */
final class TiketDapurDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TiketDapurDetail';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jumlah' => 'decimal:4', 'Pilihan' => 'array', 'Status' => StatusBarisTiket::class];
    }
}

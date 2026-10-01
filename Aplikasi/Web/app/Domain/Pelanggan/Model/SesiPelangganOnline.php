<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Sesi masuk pembeli toko online (F-17 bagian 3). Token acaknya hanya ada di cookie peramban; yang tersimpan di sini
 * hash-nya, jadi kebocoran tabel tidak membuka sesi siapa pun. `DicabutPada` diisi saat keluar.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPelanggan
 * @property string $HashToken
 * @property Carbon $KedaluwarsaPada
 * @property Carbon|null $TerakhirDipakaiPada
 * @property Carbon|null $DicabutPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class SesiPelangganOnline extends ModelDasar
{
    use MilikTenant;

    protected $table = 'SesiPelangganOnline';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'KedaluwarsaPada' => 'datetime',
            'TerakhirDipakaiPada' => 'datetime',
            'DicabutPada' => 'datetime',
        ];
    }
}

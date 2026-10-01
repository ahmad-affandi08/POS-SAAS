<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Kode masuk WhatsApp pembeli toko online (F-17 bagian 3). Nomor HP, kode, token daftar, dan IP hanya disimpan sebagai
 * HMAC: baris ini tidak pernah memuat data pribadi yang bisa dibaca.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $HashNoHp
 * @property string $HashKode
 * @property int $Percobaan
 * @property Carbon $KedaluwarsaPada
 * @property Carbon|null $DipakaiPada
 * @property string|null $HashTokenDaftar
 * @property Carbon|null $TokenDaftarKedaluwarsaPada
 * @property string|null $HashIp
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class KodeMasukPelanggan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KodeMasukPelanggan';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Percobaan' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Percobaan' => 'integer',
            'KedaluwarsaPada' => 'datetime',
            'DipakaiPada' => 'datetime',
            'TokenDaftarKedaluwarsaPada' => 'datetime',
        ];
    }
}

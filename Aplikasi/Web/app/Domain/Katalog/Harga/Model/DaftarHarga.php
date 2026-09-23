<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Illuminate\Support\Carbon;

/**
 * Daftar harga (price list) tenant, lapis 3 price engine F-03 (PRD §15.3). Kondisi null = berlaku untuk semua:
 * `IdOutlet` (daftar `Outlet.Id`), `Kanal`, `TierPelanggan`, rentang UTC `[MulaiPada, SelesaiPada)`. Urutan pilih:
 * `Prioritas` tertinggi, lalu paling spesifik, lalu `Uuid` terkecil (`PenentuHarga`). Tidak pernah dihapus, hanya
 * dinonaktifkan (`Aktif = false`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property list<int>|null $IdOutlet
 * @property KanalPenjualan|null $Kanal
 * @property string|null $TierPelanggan
 * @property Carbon|null $MulaiPada
 * @property Carbon|null $SelesaiPada
 * @property int $Prioritas
 * @property bool $Aktif
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class DaftarHarga extends ModelDasar
{
    use MilikTenant;

    protected $table = 'DaftarHarga';

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdOutlet' => null,
        'Kanal' => null,
        'TierPelanggan' => null,
        'MulaiPada' => null,
        'SelesaiPada' => null,
        'Prioritas' => 0,
        'Aktif' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'IdOutlet' => 'array',
            'Kanal' => KanalPenjualan::class,
            'MulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
            'Prioritas' => 'integer',
            'Aktif' => 'boolean',
        ];
    }
}

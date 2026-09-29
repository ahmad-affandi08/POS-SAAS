<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\JenisNotifikasiPengguna;
use Illuminate\Support\Carbon;

/**
 * Notifikasi persisten Aplikasi Owner. Push FCM hanya pengantar; daftar API selalu membaca tabel ini.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property JenisNotifikasiPengguna $Jenis
 * @property string $Kunci
 * @property string $Judul
 * @property string $Isi
 * @property array<string, string>|null $Data
 * @property Carbon|null $DibacaPada
 * @property Carbon|null $DikirimPada
 * @property Carbon|null $GagalPada
 * @property string|null $PesanGalat
 * @property Carbon|null $DibuatPada
 */
final class NotifikasiPengguna extends ModelDasar
{
    use MilikTenant;

    protected $table = 'NotifikasiPengguna';

    protected $attributes = [
        'Data' => null,
        'DibacaPada' => null,
        'DikirimPada' => null,
        'GagalPada' => null,
        'PesanGalat' => null,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisNotifikasiPengguna::class,
            'Data' => 'array',
            'DibacaPada' => 'datetime',
            'DikirimPada' => 'datetime',
            'GagalPada' => 'datetime',
        ];
    }
}
